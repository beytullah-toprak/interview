<?php

namespace Turkpin\InterviewTest\Helpers;

/**
 * Basit dosya tabanlı loglayıcı. API hatalarını storage/logs/api.log altına yazar.
 */
class Logger
{
    private static string $logFile = __DIR__ . '/../../storage/logs/api.log';

    public static function error(string $message, array $context = []): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $line = sprintf(
            '[%s] %s %s%s',
            date('Y-m-d H:i:s'),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
            PHP_EOL
        );

        file_put_contents(self::$logFile, $line, FILE_APPEND);
    }
}
