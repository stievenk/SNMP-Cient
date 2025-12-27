<?php
use Koyabu\SnmpClient\SnmpClient;
use Koyabu\SnmpClient\SnmpLogger;
Use Koyabu\TelegramAPI\Telegram;

class Monitoring extends Controller {
    public $error;
    public $config;
    public $conn;

    public $Telegram;
    public $notif = false;

    public $Servers = [];

    public function run() {
        if (!empty($this->config['telegram'])) {
            $this->notif = $this->config['telegram']['notif'];
            $this->Telegram = new Telegram($this->config['telegram']);
        }
        $this->serverList();
    }

    protected function serverList() {
        $g = $this->select("select
        s.id as sid, 
        s.*, sp.*, si.* 
       from `servers` as s
        left join server_snmp_profiles as sp on sp.server_id = s.id
        left join server_system_info as si on si.server_id = s.id
        ");
        while($t = $this->fetch_assoc($g)) {
            $this->debug($t['hostname']." Starting Monitoring SNMPv{$t['snmp_version']}");
            if (empty($t['snmp_version'])
            or empty($t['community'])) {
                $this->debug($t['hostname'], ". Skipped no SNMP Profile");
                continue;
            }
            if (empty($t['ip_public']) and empty($t['ip_local'])) {
                $this->debug($t['hostname']." Skipped no IP Address");
                continue;
            }
            if ($t['snmp_version'] == 3) {
                $snmp = new SnmpClient(
                    $t['ip_public'] ?? $t['ip_local'],
                    3,
                    [
                        'sec_name' => $t['v3_user'],
                        'sec_level' => $t['v3_sec_level'],
                        'auth_proto' => $t['v3_auth_proto'],
                        'auth_pass' => $t['v3_auth_pass'],
                        'priv_proto' => $t['v3_priv_proto'],
                        'priv_pass' => $t['v3_priv_pass']
                    ], new SnmpLogger('snmp.log')
                );
            } else {
                $snmp = new SnmpClient(
                    $t['ip_public'] ?? $t['ip_local'],
                    2,
                    ['community' => $t['community']]
                );
            }
            $SysInfo = $snmp->systemInfo();
            $SysInfo['uptime'] = $this->timeticksToReadable($SysInfo['server_uptime'] ?? '');
            if (empty($SysInfo['hostname'])) {
                $this->save('servers',[
                    'id' => $t['sid'],
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_active' => 0
                ]);

                $gif = $this->select("select * from `server_ip_addresses` where `server_id` = '". $t['sid'] ."'");
                while($v = $this->fetch_assoc($gif)) {
                    if (!$v['if_name']) { continue; }
                    $this->save('server_interfaces_history',[
                        'server_id' => $t['sid'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'if_name' => $v['if_name'],
                        'in_bps' => 0, 
                        'out_bps' => 0,
                        'delta_time' => 0
                    ]);
                }
                // continue;
                if ($t['is_active'] == 1) {
                    $this->sendWarning("WARNING! Server not response",$t['sid']);
                    if (!empty($t['telegram_notif'])) {
                        $this->sendWarning("WARNING! Server not response ",$t['sid'],$t['telegram_notif']);
                    }
                }
                continue;
            } else {
                if ($t['is_active'] == 0) {
                    $this->sendWarning("Server UP",$t['sid']);
                    if (!empty($t['telegram_notif'])) {
                        $this->sendWarning("Server UP ".$SysInfo['uptime'],$t['sid'],$t['telegram_notif']);
                    }
                }
            }

            // print_r([$SysInfo]);
            $t['snmp'] = $SysInfo; 
            $t['snmp']['cpu_cores'] = $snmp->cpuCores();
            $t['snmp']['cpu_model'] = $snmp->cpuModel();
            $this->Servers[] = $t;
            $server_id = $t['sid'];
            // $mem = $snmp->disks(); 
            // print_r($mem); continue;
            $this->serverUpdateSys($t);
            $this->debug($t['hostname']." => {$t['snmp']['uptime']}");
            $this->debug("{$t['snmp']['cpu_model']} {$t['snmp']['cpu_cores']} Cores");
            $this->saveCPULoad($snmp->cpuLoad(),$server_id);

            $this->saveTraffiData($snmp->interfaces(),$server_id);

            $this->saveMemoryData($snmp->memory(),$server_id);

            $this->saveDiskData($snmp->disks(),$server_id);

            $this->saveIpAddresses($snmp->ipAddresses(),$server_id);
        }
    }

    public function serverUpdateSys($params) {
        $this->save('servers',[
            'id' => $params['sid'],
            'updated_at' => date('Y-m-d H:i:s'),
            'location' => $params['snmp']['location'],
            'os' => $params['snmp']['os'],
            'cpu_cores' => $params['snmp']['cpu_cores'],
            'cpu_model' => $params['snmp']['cpu_model'],
            'hostname' => $params['snmp']['hostname'],
            'uptime' => $params['snmp']['uptime'],
            'is_active' => 1
        ]);
    }

    public function saveCPULoad($params,$server_id) {
        // print_r($params);
        $this->save('server_cpu_history',[
            'server_id' => $server_id,
            'created_at' => date('Y-m-d H:i:s'),
            'load_1' => $params['1min'],
            'load_5' => $params['5min'],
            'load_15' => $params['15min']
        ]);
        $this->debug("CPU Load Saved");
    }

    public function saveTraffiData($params,$server_id) {
        // print_r($params);
        $IN = 0; 
        $OUT = 0;

        if (!empty($params) and is_array($params)) {
            foreach ($params as $k => $v) {
                $this->save('server_traffic_history',[
                    'server_id' => $server_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'if_name' => $v['device'],
                    'in_bps' => $v['rx_bytes'],
                    'out_bps' => $v['tx_bytes']
                ]);
                $DeltaData = $this->getTrafficData($server_id,$v['device']);
                $this->save('server_interfaces_history',[
                    'server_id' => $server_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'if_name' => $v['device'],
                    'in_bps' => $DeltaData['in']['bps'] ?? 0, 
                    'out_bps' => $DeltaData['out']['bps'] ?? 0,
                    'delta_time' => $DeltaData['deltaTime'] ?? 0
                ]);
                $IN+= $DeltaData['in']['bps'];
                $OUT+= $DeltaData['out']['bps'];

            }
            
            if ($IN > $this->config['warning']['traffic'] or $OUT > $this->config['warning']['traffic']) {
                $this->sendWarning("Warning Traffic Usage Rx: ". 
                round($IN / 1000000,4) ."Mbps Tx: ". round($OUT / 1000000,4) ."Mbps",$server_id);
            } else {
                $this->debug("Traffic History Saved Rx: ". round($IN / 1000000,4) ."Mbps Tx: ". round($OUT / 1000000,4) ."Mbps");
            }
        }
    }

    public function getTrafficData($server_id,$if_name) {
       $g = $this->select("select * from `server_traffic_history` 
       where `server_id` = '". $server_id ."' and 
       `if_name` = '". $if_name ."' order by `created_at` desc limit 2");
        while($t = $this->fetch_assoc($g)) {
            $d[] = $t;
        }
        $deltaTime = strtotime($d[0]['created_at']) - strtotime($d[1]['created_at']);
        
        $in = max(0, $d[0]['in_bps'] - $d[1]['in_bps']) / $deltaTime;
        $out = max(0, $d[0]['out_bps'] - $d[1]['out_bps']) / $deltaTime;

        $data = [
            'deltaTime' => $deltaTime,
            'in' => [
                'bps' => $in,
                'mbps' => $in / 1000000,
                'kbps' => $in / 1000
            ],
            'out' => [
                'bps' => $out,
                'mbps' => $out / 1000000,
                'kbps' => $out / 1000
            ]
        ];
        // print_r($data);
        return $data;
    }

    public function saveMemoryData($params,$server_id) {
        // print_r($params);
        $this->save('server_memory_history',[
            'server_id' => $server_id,
            'created_at' => date('Y-m-d H:i:s'),
            'ram_total' => $params['physical']['total'],
            'ram_used' => $params['physical']['used'],
            'ram_free' => $params['physical']['free'],
            'swap_total' => $params['swap']['total'],
            'swap_used' => $params['swap']['used'],
            'swap_free' => $params['swap']['free']
        ]);
        // Mem Notif
        $p_prc = $params['physical']['total'] > 0 ? 
        round(($params['physical']['used'] / $params['physical']['total']) * 100,2) : 0;
        $s_prc = $params['swap']['total'] > 0 ? 
        round(($params['swap']['used'] / $params['swap']['total']) * 100,2) : 0;
        if ($params['swap']['total'] and $params['physical']['total']) {
        $all_prc = round(($params['physical']['used'] + $params['swap']['used']) /
                         ($params['physical']['total'] + $params['swap']['total']) * 100, 2);
        } else {
            $all_prc = 0;
        }
        $text = "Memory Used RAM:{$p_prc}%, Swap:{$s_prc}%, Total:{$all_prc}%";
        if ((float)$s_prc > $this->config['warning']['swap']
         or ((float) $s_prc <= 0 and (float) $p_prc > $this->config['warning']['ram']
         or (float) $all_prc > ($this->config['warning']['all_mem'] ?? $this->config['warning']['ram']))) {
            $this->sendWarning("WARNING! ".$text,$server_id);
        } else {
            $this->debug($text);
        }
    }

    public function saveDiskData($params,$server_id) {
        // print_r($params);
        foreach ($params as $k => $v) {
            $data = [
                'server_id' => $server_id,
                'created_at' => date('Y-m-d H:i:s'),
                'disk_name' => $v['mount'],
                'disk_total' => $v['total'],
                'disk_used' => $v['used'],
                'disk_free' => $v['total'] - $v['used']
            ];
            $fd = [];
            foreach ($data as $k => $v) {
                $fd[] = "`{$k}` = '". $this->escape_string($v) ."'";
            }
            $this->query("REPLACE INTO server_disks
            SET ". implode(", ",$fd) ."");
        }
        $this->debug("Disk History Saved");
    }

    public function saveIpAddresses($params,$server_id) {
        // print_r($params);
        foreach ($params as $k => $v) {
            $IP = trim(trim($v['ip'],'IpAddress:'));
            $SN = trim(trim($v['netmask'],'IpAddress:'));
            $this->query("REPLACE INTO server_ip_addresses
            SET server_id='{$server_id}', if_name='{$v['device']}', ip_address='{$IP}', netmask='{$SN}'");
        }
        $this->debug("IP Address Saved");
    }

    function timeticksToReadable(string $timeticks): string {
        // Ambil angka di dalam tanda kurung
        if (preg_match('/\((\d+)\)/', $timeticks, $m)) {
            $ticks = (int)$m[1];
            
        } else {
            $ticks = (int)$timeticks;
        }
        $seconds = $ticks / 100; // 1 tick = 1/100 detik

            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            $days = floor($hours / 24);
            $hours = $hours % 24;

            return sprintf('%d hari %d jam %d menit %.2f detik', $days, $hours, $minutes, $secs);

        return $timeticks; // fallback
    }

    public function sendWarning($text,$server_id,$user='') {
        if ($this->notif) {
            $user = $user ?? $this->config['telegram']['user'];
            if ($user) {
                    $t = $this->get([ 'table' => 'servers', 'field' => 'id', 'data' => $server_id ]);
                    $text = "[{$t['hostname']}] {$text}";
                    $this->debug($text);
                    $this->Telegram->sendMessage($user, $text);
            }
        }
    }

}
?>