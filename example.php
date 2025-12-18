<?php
namespace Koyabu\SnmpClient;

require 'vendor/autoload.php';
$snmp = new SnmpClient(
    '127.0.0.1',
    2,
    ['community' => 'monitoronly']
);

$if1 = $snmp->interfaces();
sleep(300);
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