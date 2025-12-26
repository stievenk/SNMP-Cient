<?php
namespace Koyabu\SnmpClient;

use Throwable;

class SnmpClient
{
    private string $host;
    private int $version;
    private array $auth;
    private SnmpLogger $logger;

    public function __construct(
        string $host,
        int $version = 2,
        array $auth = [],
        ?SnmpLogger $logger = null
    ) {
        $this->host   = $host;
        $this->version = $version;
        $this->auth   = $auth;
        $this->logger = $logger ?? new SnmpLogger();

        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    }

    /* ================= CORE ================= */

    private function snmpGetRaw(string $oid)
    {
        try {
            return $this->version === 3
                ? snmp3_get(
                    $this->host,
                    $this->auth['sec_name'],
                    $this->auth['sec_level'],
                    $this->auth['auth_proto'],
                    $this->auth['auth_pass'],
                    $this->auth['priv_proto'] ?? null,
                    $this->auth['priv_pass'] ?? null,
                    $oid
                )
                : snmp2_get($this->host, $this->auth['community'], $oid);
        } catch (Throwable $e) {
            $this->logger->error('SNMP GET failed', compact('oid'));
            throw new SnmpException($e->getMessage());
        }
    }

    private function snmpWalkRaw(string $oid): array
{
    try {
        if ($this->version === 3) {

            if ($this->auth['sec_level'] === 'authPriv') {
                return snmp3_real_walk(
                    $this->host,
                    $this->auth['sec_name'],
                    $this->auth['sec_level'],
                    $this->auth['auth_proto'],
                    $this->auth['auth_pass'],
                    $this->auth['priv_proto'],
                    $this->auth['priv_pass'],
                    $oid
                );
            }

            // authNoPriv
            return snmp3_real_walk(
                $this->host,
                $this->auth['sec_name'],
                $this->auth['sec_level'],
                $this->auth['auth_proto'],
                $this->auth['auth_pass'],
                null,
                null,
                $oid
            );
        }

        return snmp2_real_walk($this->host, $this->auth['community'], $oid);

    } catch (\Throwable $e) {
        $this->logger->error('SNMP WALK failed', ['oid' => $oid, 'error' => $e->getMessage()]);
        throw new SnmpException($e->getMessage());
    }
}


    private function cleanValue($value): ?string
    {
        if ($value === false || $value === null) return null;
        return trim(preg_replace('/^[A-Z\-]+:\s*/', '', (string)$value));
    }

    protected function get(string $oid): ?string
    {
        return $this->cleanValue($this->snmpGetRaw($oid));
    }

    protected function walk(string $oid): array
    {
        $raw = $this->snmpWalkRaw($oid) ?: [];
        foreach ($raw as $k => $v) {
            $raw[$k] = $this->cleanValue($v);
        }
        return $raw;
    }

    /* ================= PARSERS ================= */

    private function parseIndex(string $oid): ?int
    {
        return preg_match('/\.(\d+)$/', $oid, $m) ? (int)$m[1] : null;
    }

    private function parseIpFromOid(string $oid): ?string
    {
        return preg_match('/(\d+\.\d+\.\d+\.\d+)$/', $oid, $m) ? $m[1] : null;
    }

    private function normalizeStringByIndex(array $walk): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if (($i = $this->parseIndex($oid)) !== null) {
                $out[$i] = (string)$val;
            }
        }
        return $out;
    }

    private function normalizeCounterByIndex(array $walk): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if (($i = $this->parseIndex($oid)) !== null) {
                $out[$i] = (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT);
            }
        }
        return $out;
    }

    private function normalizeIpMap(array $walk, bool $numeric = false): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if ($ip = $this->parseIpFromOid($oid)) {
                $out[$ip] = $numeric
                    ? (int)filter_var($val, FILTER_SANITIZE_NUMBER_INT)
                    : $val;
            }
        }
        return $out;
    }

    /* ================= SYSTEM ================= */

    public function systemInfo(): array
    {
        return [
            'hostname' => $this->get('.1.3.6.1.2.1.1.5.0'),
            'os'       => $this->get('.1.3.6.1.2.1.1.1.0'),
            'location' => $this->get('.1.3.6.1.2.1.1.6.0'),
            'snpm_uptime'   => $this->get('.1.3.6.1.2.1.1.3.0'),
            'server_uptime' => $this->get('.1.3.6.1.2.1.25.1.1.0')
        ];
    }

    /* ================= CPU ================= */

    public function cpuLoad(): array
    {
        $l = $this->normalizeStringByIndex(
            $this->walk('.1.3.6.1.4.1.2021.10.1.3')
        );
        // print_r($l);

        return [
            '1min'  => (float)($l[1] ?? 0),
            '5min'  => (float)($l[2] ?? 0),
            '15min' => (float)($l[3] ?? 0),
        ];
    }

    public function cpuCores(): int
    {
        // Cara cepat (jika tersedia)
        $num = $this->get('.1.3.6.1.4.1.2021.11.9.0');
        if (is_numeric($num) && (int)$num > 0) {
            return (int)$num;
        }

        // Fallback: hitung hrProcessorLoad
        $cores = $this->walk('.1.3.6.1.2.1.25.3.3.1.2');
        return count($cores);
    }

    public function cpuModel(): ?string
{
    $types = $this->walk('.1.3.6.1.2.1.25.3.2.1.2');
    $descr = $this->walk('.1.3.6.1.2.1.25.3.2.1.3');

    foreach ($types as $oid => $_val) {

        // Ambil index terakhir (196608, 196609, dst)
        $index = substr(strrchr($oid, '.'), 1);

        // hrDeviceType = processor
        if (isset($descr[".1.3.6.1.2.1.25.3.2.1.3.$index"])) {
            $desc = $descr[".1.3.6.1.2.1.25.3.2.1.3.$index"];

            // Filter hanya CPU (hindari network, dll)
            if (stripos($desc, 'Intel') !== false || stripos($desc, 'AMD') !== false) {
                return trim($desc);
            }
        }
    }

    return null;
}




    /* ================= MEMORY ================= */

    public function memory(): array
    {
        $total = (int)$this->get('.1.3.6.1.4.1.2021.4.5.0');
        $free  = (int)$this->get('.1.3.6.1.4.1.2021.4.6.0');

        $TotalAll  = (int)$this->get('.1.3.6.1.4.1.2021.4.11.0');

        $swapT = (int)$this->get('.1.3.6.1.4.1.2021.4.3.0');
        $swapF = (int)$this->get('.1.3.6.1.4.1.2021.4.4.0');

        $TotalUsed = max(0, $swapT - $swapF) + max(0, $total - $free);


        return [
            'physical' => [
                'total' => $total,
                'used'  => max(0, $total - $free),
                'free'  => $free
            ],
            'swap' => [
                'total' => $swapT,
                'used'  => max(0, $swapT - $swapF),
                'free'  => $swapF
            ],
            'total' => $TotalAll,
            'total_used' => $TotalUsed,
            'total_free' => $TotalAll - $TotalUsed
        ];
    }

    /* ================= DISK ================= */

    public function disks(): array
    {
        $mnt = $this->normalizeStringByIndex(
            $this->walk('.1.3.6.1.4.1.2021.9.1.2')
        );
        $tot = $this->normalizeCounterByIndex(
            $this->walk('.1.3.6.1.4.1.2021.9.1.6')
        );
        $use = $this->normalizeCounterByIndex(
            $this->walk('.1.3.6.1.4.1.2021.9.1.8')
        );

        $out = [];
        foreach ($mnt as $i => $m) {
            $out[] = [
                'mount' => $m,
                'total_kb' => $tot[$i] ?? 0,
                'used_kb'  => $use[$i] ?? 0
            ];
        }
        return $out;
    }

    /* ================= NETWORK ================= */

    public function interfaces(): array
    {
        $names = $this->normalizeStringByIndex(
            $this->walk('.1.3.6.1.2.1.31.1.1.1.1')
        );
        $rx = $this->normalizeCounterByIndex(
            $this->walk('.1.3.6.1.2.1.31.1.1.1.6')
        );
        $tx = $this->normalizeCounterByIndex(
            $this->walk('.1.3.6.1.2.1.31.1.1.1.10')
        );

        $out = [];
        foreach ($names as $i => $n) {
            $out[$i] = [
                'device' => $n,
                'rx_bytes' => $rx[$i] ?? 0,
                'tx_bytes' => $tx[$i] ?? 0,
            ];
        }
        return $out;
    }

    public function ipAddresses(): array
    {
        $ips = $this->normalizeIpMap(
            $this->walk('.1.3.6.1.2.1.4.20.1.1')
        );
        $idx = $this->normalizeIpMap(
            $this->walk('.1.3.6.1.2.1.4.20.1.2'),
            true
        );
        $mask = $this->normalizeIpMap(
            $this->walk('.1.3.6.1.2.1.4.20.1.3')
        );
        $names = $this->normalizeStringByIndex(
            $this->walk('.1.3.6.1.2.1.31.1.1.1.1')
        );

        $out = [];
        foreach ($ips as $ip => $addr) {
            $i = $idx[$ip] ?? null;
            $out[] = [
                'ip' => $addr,
                'interface_index' => $i,
                'device' => $names[$i] ?? 'unknown',
                'netmask' => $mask[$ip] ?? null
            ];
        }
        return $out;
    }

    /* ================= PORTS ================= */

    public function listeningPorts(): array
    {
        $walk = $this->walk('.1.3.6.1.2.1.6.13.1.3');

        if (!$walk) {
            return [];
        }

        $ports = [];

        foreach ($walk as $oid => $value) {
            $port = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

            // valid TCP port
            if ($port > 0 && $port <= 65535) {
                $ports[$port] = true;
            }
        }

        return array_keys($ports);
    }


    /* ================= TRAFFIC ================= */

    public static function trafficDelta(array $old, array $new): array
    {
        $d = [];
        foreach ($new as $i => $n) {
            if (!isset($old[$i])) continue;
            $d[$i] = [
                'device' => $n['device'],
                'rx_bps' => max(0, $n['rx_bytes'] - $old[$i]['rx_bytes']),
                'tx_bps' => max(0, $n['tx_bytes'] - $old[$i]['tx_bytes']),
            ];
        }
        return $d;
    }
}
?>