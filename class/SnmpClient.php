<?php
namespace Koyabu\SnmpClient;
use Koyabu\SnmpClient\SnmpException;
use Koyabu\SnmpClient\SnmpLogger;

class SnmpClient
{
    private string $host;
    private int $version;
    private array $auth;
    private SnmpLogger $logger;

    public function __construct(string $host, int $version = 2, array $auth = [], ?SnmpLogger $logger = null)
    {
        $this->host   = $host;
        $this->version = $version;
        $this->auth   = $auth;
        $this->logger = $logger ?? new SnmpLogger();
    }

    /* ================= CORE ================= */

    private function snmpGetRaw(string $oid)
    {
        try {
            if ($this->version === 3) {
                return snmp3_get(
                    $this->host,
                    $this->auth['sec_name'],
                    $this->auth['sec_level'],
                    $this->auth['auth_proto'],
                    $this->auth['auth_pass'],
                    $this->auth['priv_proto'] ?? null,
                    $this->auth['priv_pass'] ?? null,
                    $oid
                );
            }

            return snmp2_get($this->host, $this->auth['community'], $oid);
        } catch (Throwable $e) {
            $this->logger->error('SNMP GET failed', ['oid' => $oid, 'error' => $e->getMessage()]);
            throw new SnmpException($e->getMessage());
        }
    }

    private function snmpWalkRaw(string $oid): array
    {
        try {
            if ($this->version === 3) {
                return snmp3_walk(
                    $this->host,
                    $this->auth['sec_name'],
                    $this->auth['sec_level'],
                    $this->auth['auth_proto'],
                    $this->auth['auth_pass'],
                    $this->auth['priv_proto'] ?? null,
                    $this->auth['priv_pass'] ?? null,
                    $oid
                );
            }

            return snmp2_walk($this->host, $this->auth['community'], $oid);
        } catch (Throwable $e) {
            $this->logger->error('SNMP WALK failed', ['oid' => $oid]);
            throw new SnmpException($e->getMessage());
        }
    }

    private function clean($value)
    {
        if ($value === false || $value === null) {
            return null;
        }
        return trim(preg_replace('/^[A-Z\-]+:\s*/', '', (string)$value));
    }

    protected function get(string $oid)
    {
        return $this->clean($this->snmpGetRaw($oid));
    }

    protected function walk(string $oid): array
    {
        $raw = $this->snmpWalkRaw($oid);
        return array_map([$this, 'clean'], $raw ?: []);
    }

    /* ================= SYSTEM ================= */

    public function systemInfo(): array
    {
        return [
            'hostname' => $this->get('.1.3.6.1.2.1.1.5.0'),
            'os'       => $this->get('.1.3.6.1.2.1.1.1.0'),
            'location' => $this->get('.1.3.6.1.2.1.1.6.0'),
            'uptime'   => $this->get('.1.3.6.1.2.1.1.3.0')
        ];
    }

    /* ================= CPU ================= */

    public function cpuLoad(): array
    {
        $load = $this->walk('.1.3.6.1.4.1.2021.10.1.3');
        return [
            '1min'  => (float)($load[0] ?? 0),
            '5min'  => (float)($load[1] ?? 0),
            '15min' => (float)($load[2] ?? 0)
        ];
    }

    /* ================= MEMORY ================= */

    public function memory(): array
    {
        $pt = (int)$this->get('.1.3.6.1.4.1.2021.4.5.0');
        $pf = (int)$this->get('.1.3.6.1.4.1.2021.4.11.0');
        $st = (int)$this->get('.1.3.6.1.4.1.2021.4.3.0');
        $sf = (int)$this->get('.1.3.6.1.4.1.2021.4.4.0');

        return [
            'physical' => [
                'total' => $pt,
                'used'  => $pt - $pf,
                'free'  => $pf
            ],
            'swap' => [
                'total' => $st,
                'used'  => $st - $sf,
                'free'  => $sf
            ]
        ];
    }

    /* ================= DISK ================= */

    public function disks(): array
    {
        $mounts = $this->walk('.1.3.6.1.4.1.2021.9.1.2');
        $total  = $this->walk('.1.3.6.1.4.1.2021.9.1.6');
        $used   = $this->walk('.1.3.6.1.4.1.2021.9.1.8');

        $out = [];
        foreach ($mounts as $i => $mnt) {
            $out[] = [
                'mount' => $mnt,
                'total' => (int)($total[$i] ?? 0),
                'used'  => (int)($used[$i] ?? 0)
            ];
        }
        return $out;
    }

    /* ================= NETWORK ================= */

    public function interfaces(): array
    {
        $name = $this->walk('.1.3.6.1.2.1.2.2.1.2');
        $rx   = $this->walk('.1.3.6.1.2.1.2.2.1.10');
        $tx   = $this->walk('.1.3.6.1.2.1.2.2.1.16');

        $ifs = [];
        foreach ($name as $i => $dev) {
            $ifs[$i + 1] = [
                'device' => $dev,
                'rx' => (int)($rx[$i] ?? 0),
                'tx' => (int)($tx[$i] ?? 0)
            ];
        }
        return $ifs;
    }

    public function ipAddresses(): array
    {
        $ips  = $this->walk('.1.3.6.1.2.1.4.20.1.1');
        $ifid = $this->walk('.1.3.6.1.2.1.4.20.1.2');
        $mask = $this->walk('.1.3.6.1.2.1.4.20.1.3');

        $out = [];
        foreach ($ips as $i => $ip) {
            $out[] = [
                'ip' => $ip,
                'interface_index' => (int)($ifid[$i] ?? 0),
                'netmask' => $mask[$i] ?? null
            ];
        }
        return $out;
    }

    /* ================= PORTS ================= */

    public function listeningPorts(): array
    {
        $ports = $this->walk('.1.3.6.1.2.1.6.13.1.3');
        return array_values(array_unique(array_map('intval', $ports)));
    }

    /* ================= TRAFFIC DELTA ================= */

    public static function trafficDelta(array $old, array $new): array
    {
        $delta = [];
        foreach ($new as $idx => $iface) {
            if (!isset($old[$idx])) continue;
            $delta[$idx] = [
                'device' => $iface['device'],
                'rx_bps' => max(0, $iface['rx'] - $old[$idx]['rx']),
                'tx_bps' => max(0, $iface['tx'] - $old[$idx]['tx'])
            ];
        }
        return $delta;
    }
}
?>