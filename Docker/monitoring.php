<?php
set_time_limit(0); // unlimited
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_PARSE);
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_quick_print(true);
snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);

ini_set('default_socket_timeout', 3);

use Koyabu\SnmpClient\SnmpClient;
use Koyabu\SnmpClient\SnmpLogger;
require 'vendor/autoload.php';
require 'class/Connection.php';
require 'class/Controller.php';
require 'class/Monitoring.php';

function env(string $key, $default = null)
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

ini_set('date.timezone',env('TIMEZONE', 'Asia/Makassar'));

$config = [
    'mysql' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'user' => env('DB_USER', 'snmpmon'),
        'pass' => env('DB_PASS', ''),
        'data' => env('DB_NAME', 'snmpmon')
    ],
    'webhoook_url' => env('WEBHOOK_URL', '')
];
// $Monitoring = new Form($config);

$C = new Monitoring($config);

$halt = env('SLEEPTIME', '300');
$C->debug("Starting SNMP Monitoring...");
$C->run();
echo PHP_EOL;

?>