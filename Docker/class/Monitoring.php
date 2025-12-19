<?php
use Koyabu\SnmpClient\SnmpClient;
use Koyabu\SnmpClient\SnmpLogger;

class Monitoring extends Controller {
    public $error;
    public $config;
    public $conn;

    public $Servers = [];

    public function run() {
        $this->serverList();
    }

    protected function serverList() {
        $g = $this->select("select * from `servers` as s
        left join server_snmp_profiles as sp on sp.server_id = s.id
        left join server_system_info as si on si.server_id = s.id
        ");
        while($t = $this->fetch_assoc($g)) {
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
            $SysInfo = $snmp->systemInfo();
            $SysInfo['uptime'] = $this->timeticksToReadable($SysInfo['server_uptime'] ?? '');
            // print_r([$SysInfo]);
            $t['snmp'] = $SysInfo; 
            $t['snmp']['cpu_cores'] = $snmp->cpuCores();
            $this->Servers[] = $t;
            $server_id = $t['id'];
            
            $this->serverUpdateSys($t);
            $this->debug($t['hostname']." => {$t['snmp']['uptime']}");

            $this->saveCPULoad($snmp->cpuLoad(),$server_id);

            $this->saveTraffiData($snmp->interfaces(),$server_id);

            $this->saveMemoryData($snmp->memory(),$server_id);

            $this->saveDiskData($snmp->disks(),$server_id);

            $this->saveIpAddresses($snmp->ipAddresses(),$server_id);
        }
    }

    public function serverUpdateSys($params) {
        $this->save('servers',[
            'id' => $params['id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'location' => $params['snmp']['location'],
            'os' => $params['snmp']['os'],
            'cpu_cores' => $params['snmp']['cpu_cores'],
            'hostname' => $params['snmp']['hostname'],
            'uptime' => $params['snmp']['uptime']
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
                    'in_bps' => $DeltaData['in']['bps'],
                    'out_bps' => $DeltaData['out']['bps'],
                    'delta_time' => $DeltaData['deltaTime']
                ]);
            }
            $this->debug("Traffic History Saved");
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
        $this->debug("Memory History Saved");
    }

    public function saveDiskData($params,$server_id) {
        // print_r($params);
        foreach ($params as $k => $v) {
            $data = [
                'server_id' => $server_id,
                'created_at' => date('Y-m-d H:i:s'),
                'disk_name' => $v['mount'],
                'disk_total' => $v['total_kb'],
                'disk_used' => $v['used_kb'],
                'disk_free' => $v['total_kb'] - $v['used_kb']
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
}
?>