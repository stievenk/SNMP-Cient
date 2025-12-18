<?php
namespace Koyabu\SnmpClient;

class SnmpLogger
{
    private string $file;

    public function __construct(string $file = '/var/log/snmp-monitor.log')
    {
        $this->file = $file;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $ctx  = $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        file_put_contents(
            $this->file,
            "[$date][$level] $message $ctx\n",
            FILE_APPEND | LOCK_EX
        );
    }

    public function info(string $msg, array $ctx = []): void
    {
        $this->log('INFO', $msg, $ctx);
    }

    public function error(string $msg, array $ctx = []): void
    {
        $this->log('ERROR', $msg, $ctx);
    }
}
?>