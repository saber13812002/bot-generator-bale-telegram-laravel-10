<?php

namespace App\Logging;

use Monolog\Logger;

class DatabaseLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('database');
        $logger->pushHandler(new DatabaseLogHandler($config['level'] ?? 'debug'));

        return $logger;
    }
}
