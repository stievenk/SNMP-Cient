# Koyabu\SnmpClient

`Koyabu\SnmpClient` adalah **library PHP ringan** untuk mengambil informasi server atau perangkat jaringan menggunakan **SNMP** (Simple Network Management Protocol).

Library ini cocok untuk:

- monitoring server Linux
- dashboard server / VPS
- sistem monitoring internal
- belajar SNMP dengan cara yang lebih sederhana

Mendukung **SNMP v2c dan SNMP v3**, serta sudah dirancang agar **aman, stabil, dan mudah digunakan**.

---

## ✨ Fitur Utama

- ✅ Mendukung **SNMP v2c & v3**
- ✅ Ambil data **sistem, CPU, RAM, disk, jaringan**
- ✅ Ambil **daftar IP address server**
- ✅ Ambil **port TCP yang sedang LISTEN**
- ✅ Hitung **traffic jaringan (RX / TX delta)**
- ✅ Error handling jelas dengan Exception
- ✅ Logging otomatis
- ✅ Kode modern, rapi, dan efisien

---

## 📋 Persyaratan

- PHP **7.4 atau lebih baru**
- Ekstensi PHP: `php-snmp`
- Composer

Pastikan SNMP aktif di server target (biasanya `snmpd` di Linux).

---

## 📦 Instalasi

Jika library tersedia via Composer:

```bash
composer require koyabu/snmpclient
```

Jika ini library lokal, pastikan autoload di `composer.json` sudah benar.

Jangan lupa load autoloader:

```php
require 'vendor/autoload.php';
```

---

## 🚀 Cara Menggunakan

### 1️⃣ Inisialisasi SnmpClient

#### 🔹 SNMP v2c (paling umum & mudah)

```php
use Koyabu\SnmpClient\SnmpClient;

$snmp = new SnmpClient(
    '192.168.1.1',
    2,
    ['community' => 'public']
);
```

---

#### 🔹 SNMP v3 (lebih aman)

```php
use Koyabu\SnmpClient\SnmpClient;

$authV3 = [
    'sec_name'   => 'snmpuser',
    'sec_level'  => 'authPriv',
    'auth_proto' => 'SHA',
    'auth_pass'  => 'passwordAuth',
    'priv_proto' => 'AES',
    'priv_pass'  => 'passwordPriv'
];

$snmp = new SnmpClient('192.168.1.1', 3, $authV3);
```

---

## 📊 Mengambil Data

### 🖥️ Informasi Sistem

```php
print_r($snmp->systemInfo());
```

Contoh hasil:

```php
[
  'hostname' => 'server-01',
  'os'       => 'Linux Ubuntu 22.04',
  'location' => 'Data Center',
  'uptime'   => '3 days, 04:12:55'
]
```

---

### ⚙️ Beban CPU

```php
print_r($snmp->cpuLoad());
```

```php
[
  '1min'  => 0.25,
  '5min'  => 0.30,
  '15min' => 0.28
]
```

---

### 🧠 Memori (RAM & Swap)

> Semua nilai dalam **KB**

```php
print_r($snmp->memory());
```

```php
[
  'physical' => [
    'total' => 8192000,
    'used'  => 4200000,
    'free'  => 3992000
  ],
  'swap' => [
    'total' => 2048000,
    'used'  => 128000,
    'free'  => 1920000
  ]
]
```

---

### 💽 Disk

```php
print_r($snmp->disks());
```

```php
[
  [
    'mount'    => '/',
    'total_kb' => 512000000,
    'used_kb'  => 256000000
  ],
  [
    'mount'    => '/home',
    'total_kb' => 1024000000,
    'used_kb'  => 307200000
  ]
]
```

---

### 🌐 Interface Jaringan

```php
print_r($snmp->interfaces());
```

```php
[
  2 => [
    'device'    => 'eth0',
    'rx_bytes'  => 987654321,
    'tx_bytes'  => 123456789
  ]
]
```

---

### 🧾 Daftar IP Address Server

Mengembalikan semua IP yang terdaftar di server (LAN, Docker, VPN, dsb).

```php
print_r($snmp->ipAddresses());
```

```php
[
  [
    'ip' => '103.150.191.56',
    'interface_index' => 2,
    'device' => 'eth0',
    'netmask' => '255.255.254.0'
  ],
  [
    'ip' => '172.22.0.1',
    'interface_index' => 178,
    'device' => 'ztdiyuprfr',
    'netmask' => '255.255.0.0'
  ]
]
```

> ⚠️ Catatan:
>
> - IP Docker / VPN **akan ikut terbaca**
> - Ini normal dan **bukan bug**

---

### 🔐 Port TCP yang Sedang LISTEN

```php
print_r($snmp->listeningPorts());
```

```php
[22, 80, 443, 3306]
```

📌 Hanya port **TCP LISTEN**, bukan UDP.

---

## 📈 Hitung Traffic Jaringan (RX / TX)

```php
$first  = $snmp->interfaces();
sleep(60);
$second = $snmp->interfaces();

$delta = SnmpClient::trafficDelta($first, $second);

print_r($delta);
```

Hasil (dalam **byte per interval**):

```php
[
  2 => [
    'device' => 'eth0',
    'rx_bps' => 102400,
    'tx_bps' => 204800
  ]
]
```

> Jika ingin **bit per detik (bps)**:

```php
$iface['rx_bps'] = ($iface['rx_bps'] * 8) / 60;
```

---

## ❌ Penanganan Error

Semua error akan melempar `SnmpException`.

```php
use Koyabu\SnmpClient\SnmpException;

try {
    $snmp->systemInfo();
} catch (SnmpException $e) {
    echo "SNMP Error: " . $e->getMessage();
}
```

---

## 📝 Logging

Default log:

```
/var/log/snmp-monitor.log
```

Custom log:

```php
use Koyabu\SnmpClient\SnmpLogger;

$logger = new SnmpLogger(__DIR__ . '/snmp.log');

$snmp = new SnmpClient(
    '192.168.1.1',
    2,
    ['community' => 'public'],
    $logger
);
```

---

## ✅ Penutup

Library ini dirancang agar:

- **mudah dipakai user awam**
- **cukup kuat untuk produksi**
- **tidak ribet SNMP raw**

Silakan dikembangkan lebih lanjut sesuai kebutuhan monitoring Anda.
