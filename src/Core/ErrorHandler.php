<?php

namespace CureRx\Core;

class ErrorHandler
{
    public static function register(): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/app.log';

        set_exception_handler(function (\Throwable $e) use ($logFile): void {
            $entry = sprintf(
                "[%s] EXCEPTION %s: %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            error_log($entry, 3, $logFile);
            Response::error('Internal server error', 500);
        });

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($logFile): bool {
            $entry = sprintf(
                "[%s] ERROR %d: %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                $errno,
                $errstr,
                $errfile,
                $errline
            );
            error_log($entry, 3, $logFile);
            return true;
        });
    }
}
