# Koyabu\SnmpClient

`Koyabu\SnmpClient` adalah pustaka PHP ringan dan modern untuk berinteraksi dengan perangkat yang mendukung SNMP (v2c dan v3). Pustaka ini menyediakan antarmuka yang sederhana untuk mengambil data umum seperti informasi sistem, penggunaan CPU, memori, disk, dan statistik jaringan.

## Fitur

- Dukungan untuk SNMPv2c dan SNMPv3.
- Metode siap pakai untuk mengambil metrik umum (CPU, memori, disk, jaringan).
- Penanganan _exception_ yang jelas untuk kegagalan koneksi.
- Logging error yang dapat dikonfigurasi.
- Fungsi utilitas untuk menghitung selisih lalu lintas jaringan (_traffic delta_).
- Kode modern dengan _type-hinting_ dan standar PSR.

## Persyaratan

- PHP 7.4 atau lebih baru
- Ekstensi `php-snmp`
- Composer

## Instalasi

Pustaka ini dirancang untuk digunakan dengan Composer. Untuk menambahkannya ke proyek Anda, jalankan perintah berikut:

```bash
composer require koyabu/snmpclient
```

_(Catatan: Perintah di atas mengasumsikan pustaka Anda tersedia di Packagist. Jika ini adalah pustaka lokal, pastikan `autoload` di `composer.json` Anda sudah dikonfigurasi dengan benar)._

Jangan lupa untuk menyertakan autoloader Composer di file PHP Anda:

```php
require 'vendor/autoload.php';
```

## Penggunaan

### Inisialisasi Klien

Pertama, buat instance dari `SnmpClient`. Anda perlu menyediakan host, versi SNMP, dan kredensial otentikasi.

**SNMPv2c:**

Untuk v2c, kredensialnya adalah `community string`.

```php
use Koyabu\SnmpClient\SnmpClient;

$snmp = new SnmpClient(
    '192.168.1.1',
    2, // Versi SNMP
    ['community' => 'public']
);
```

**SNMPv3:**

Untuk v3, kredensialnya lebih kompleks dan mencakup tingkat keamanan, protokol, dan kata sandi.

```php
use Koyabu\SnmpClient\SnmpClient;

$authV3 = [
    'sec_name'   => 'myuser',
    'sec_level'  => 'authPriv', // noAuthNoPriv, authNoPriv, atau authPriv
    'auth_proto' => 'SHA',      // MD5 atau SHA
    'auth_pass'  => 'myAuthPassword',
    'priv_proto' => 'AES',      // DES atau AES
    'priv_pass'  => 'myPrivPassword'
];

$snmp = new SnmpClient('192.168.1.1', 3, $authV3);
```

### Mengambil Data

Semua metode pengambilan data akan mengembalikan `array` atau `null` jika terjadi kegagalan.

#### Informasi Sistem

```php
$systemInfo = $snmp->systemInfo();
print_r($systemInfo);
```

_Contoh Output:_

```
[
    'hostname' => 'my-linux-server',
    'os'       => 'Linux my-linux-server 5.4.0-121-generic ...',
    'location' => 'Server Room',
    'uptime'   => '1 day, 2:30:45.12'
]
```

#### Beban CPU

```php
$cpuLoad = $snmp->cpuLoad();
print_r($cpuLoad);
```

_Contoh Output:_

```
[
    '1min'  => 0.55,
    '5min'  => 0.62,
    '15min' => 0.60
]
```

#### Penggunaan Memori

Mengembalikan penggunaan memori Fisik dan Swap dalam Kilobyte (KB).

```php
$memory = $snmp->memory();
print_r($memory);
```

_Contoh Output:_

```
[
    'physical' => [
        'total' => 8192000,
        'used'  => 4192000,
        'free'  => 4000000
    ],
    'swap' => [
        'total' => 2048000,
        'used'  => 128000,
        'free'  => 1920000
    ]
]
```

#### Penggunaan Disk

Mengembalikan daftar partisi disk dalam Kilobyte (KB).

```php
$disks = $snmp->disks();
print_r($disks);
```

_Contoh Output:_

```
[
    [
        'mount' => '/',
        'total' => 512000000,
        'used'  => 256000000
    ],
    [
        'mount' => '/home',
        'total' => 1024000000,
        'used'  => 307200000
    ]
]
```

#### Antarmuka Jaringan

Mengembalikan total byte yang diterima (rx) dan dikirim (tx) untuk setiap antarmuka.

```php
$interfaces = $snmp->interfaces();
print_r($interfaces);
```

_Contoh Output:_

```
[
    1 => [
        'device' => 'lo',
        'rx' => 12345678,
        'tx' => 12345678
    ],
    2 => [
        'device' => 'eth0',
        'rx' => 987654321,
        'tx' => 123456789
    ]
]
```

### Menghitung Selisih Lalu Lintas (Traffic Delta)

Untuk menghitung kecepatan lalu lintas jaringan (misalnya, dalam bps), Anda perlu mengambil data `interfaces()` dua kali dengan jeda waktu, lalu gunakan metode statis `trafficDelta()`.

```php
// 1. Ambil data pertama
$interfaces1 = $snmp->interfaces();

// 2. Tunggu selama interval tertentu (misal, 60 detik)
$interval = 60;
sleep($interval);

// 3. Ambil data kedua
$interfaces2 = $snmp->interfaces();

// 4. Hitung selisihnya
$traffic = SnmpClient::trafficDelta($interfaces1, $interfaces2);

// 5. (Opsional) Hitung kecepatan dalam bit per detik (bps)
foreach ($traffic as $index => &$iface) {
    // Hasil dari trafficDelta adalah dalam bytes. Kalikan 8 untuk bit.
    // Bagi dengan interval untuk mendapatkan rate per detik.
    $iface['rx_bps'] = ($iface['rx_bps'] * 8) / $interval;
    $iface['tx_bps'] = ($iface['tx_bps'] * 8) / $interval;
}
unset($iface); // Hapus referensi

print_r($traffic);
```

## Penanganan Error

Jika permintaan SNMP gagal (misalnya, _timeout_, host tidak terjangkau, atau kredensial salah), sebuah `SnmpException` akan dilemparkan. Selalu bungkus pemanggilan metode dalam blok `try-catch`.

```php
use Koyabu\SnmpClient\SnmpException;

try {
    $systemInfo = $snmp->systemInfo();
} catch (SnmpException $e) {
    echo "Gagal mengambil data SNMP: " . $e->getMessage();
    // Error juga akan dicatat secara otomatis oleh logger
}
```

### Logging

Secara default, semua error dicatat ke `/var/log/snmp-monitor.log`. Anda dapat mengubah lokasi file log dengan menyediakan instance `SnmpLogger` Anda sendiri saat inisialisasi.

```php
use Koyabu\SnmpClient\SnmpClient;
use Koyabu\SnmpClient\SnmpLogger;

// Simpan log di direktori proyek
$logger = new SnmpLogger(__DIR__ . '/snmp.log');

$snmp = new SnmpClient(
    '192.168.1.1',
    2,
    ['community' => 'public'],
    $logger // Teruskan logger kustom
);
```
