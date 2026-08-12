<?php

declare(strict_types=1);

namespace App;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

class Bootstrap
{
    private static bool $bootstrapped = false;

    public static function boot(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::registerAutoloader();
        self::$bootstrapped = true;
    }

    public static function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefixes = [
                'App\\Tests\\' => APP_ROOT . '/tests/',
                'App\\' => APP_ROOT . '/src/',
            ];

            foreach ($prefixes as $prefix => $baseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, $len);
                $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require $file;
                }
                return;
            }
        });
    }
}

Bootstrap::boot();
