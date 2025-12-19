<?php
set_time_limit(0); // unlimited
use Koyabu\SnmpClient\SnmpClient;
use Koyabu\SnmpClient\SnmpLogger;
require 'vendor/autoload.php';

// $snmp = new SnmpClient(
//     '127.0.0.1',
//     2,
//     ['community' => 'monitoronly']
// );

$snmp = new SnmpClient(
    '127.0.0.1',
    3,
    [
        'sec_name' => 'snmpuser',
        'sec_level' => 'authPriv',
        'auth_proto' => 'SHA',
        'auth_pass' => 'YourPass',
        'priv_proto' => 'AES',
        'priv_pass' => 'YourPass'
    ], new SnmpLogger('snmp.log')
);

$if1 = $snmp->interfaces();
sleep(500);
$if2 = $snmp->interfaces();

$data = [
    'system' => $snmp->systemInfo(),
    'cpu'    => $snmp->cpuLoad(),
    'memory' => $snmp->memory(),
    'disk'   => $snmp->disks(),
    'ip'     => $snmp->ipAddresses(),
    'ports'  => $snmp->listeningPorts(),
    'traffic_delta' => SnmpClient::trafficDelta($if1, $if2)
];

print_r($data);

?>