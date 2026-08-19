<?php

declare(strict_types=1);

namespace App\Experiments;

use App\Persistence\ConnectionFactory;
use Throwable;

class EnvironmentCollector
{
    /**
     * Collects verifiable hardware and runtime environment details from the local system.
     *
     * @param string|null $configPath Path to experiment config file to compute SHA-256
     * @param string|null $datasetPath Path to dataset file to compute SHA-256
     * @return array<string, mixed> Structured environment manifest
     */
    public static function collect(?string $configPath = null, ?string $datasetPath = null): array
    {
        $system = self::collectSystemInfo();
        $runtime = self::collectRuntimeInfo();

        $configSha = ($configPath && is_file($configPath)) ? LineageHelper::hashFile($configPath) : 'TO_BE_COMPUTED';
        $datasetSha = ($datasetPath && is_file($datasetPath)) ? LineageHelper::hashFile($datasetPath) : 'TO_BE_COMPUTED';

        return [
            'schema_version' => '1.0.0',
            'status' => 'MEASURED',
            'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'system' => $system,
            'runtime' => $runtime,
            'visualization_environment' => [
                'browser_name' => null,
                'browser_version' => null,
                'viewport_width' => null,
                'viewport_height' => null,
                'device_pixel_ratio' => null,
                'display_scaling_factor' => null,
            ],
            'provenance_hashes' => [
                'experiment_config_sha256' => $configSha,
                'dataset_sha256' => $datasetSha,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectSystemInfo(): array
    {
        $osName = PHP_OS_FAMILY;
        $osVersion = php_uname('r') . ' ' . php_uname('v');
        $arch = php_uname('m');

        $cpuModel = null;
        $logicalCpus = null;
        $totalRamBytes = null;

        if (PHP_OS_FAMILY === 'Windows') {
            $cpuModel = getenv('PROCESSOR_IDENTIFIER') ?: null;
            $numProcessors = getenv('NUMBER_OF_PROCESSORS');
            if ($numProcessors !== false && is_numeric($numProcessors)) {
                $logicalCpus = (int)$numProcessors;
            }

            // Attempt PowerShell memory query without blocking
            try {
                $memOutput = @shell_exec('powershell -NoProfile -Command "(Get-CimInstance Win32_PhysicalMemory | Measure-Object -Property Capacity -Sum).Sum"');
                if ($memOutput !== null && is_numeric(trim($memOutput))) {
                    $totalRamBytes = (int)trim($memOutput);
                }
            } catch (Throwable) {
                // Keep null if unavailable
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            if (is_file('/proc/cpuinfo')) {
                $cpuInfo = (string)file_get_contents('/proc/cpuinfo');
                if (preg_match('/model name\s*:\s*(.+)$/m', $cpuInfo, $m)) {
                    $cpuModel = trim($m[1]);
                }
                $logicalCpus = preg_match_all('/^processor\s*:/m', $cpuInfo);
            }
            if (is_file('/proc/meminfo')) {
                $memInfo = (string)file_get_contents('/proc/meminfo');
                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $memInfo, $m)) {
                    $totalRamBytes = (int)$m[1] * 1024;
                }
            }
        }

        return [
            'os_name' => $osName,
            'os_version' => $osVersion,
            'architecture' => $arch,
            'cpu_model' => $cpuModel ?? 'UNAVAILABLE',
            'logical_cpu_count' => $logicalCpus,
            'total_ram_bytes' => $totalRamBytes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function collectRuntimeInfo(): array
    {
        $phpVersion = PHP_VERSION;
        $phpSapi = PHP_SAPI;
        $memoryLimit = ini_get('memory_limit') ?: 'UNAVAILABLE';
        $opcacheEnabled = (bool)(ini_get('opcache.enable_cli') ?: ini_get('opcache.enable'));

        $jitEnabled = false;
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status();
            $jitEnabled = (bool)($status['jit']['enabled'] ?? false);
        }

        // MySQL / PDO version
        $mysqlVersion = 'UNAVAILABLE';
        $pdoDriverVersion = 'UNAVAILABLE';
        try {
            $pdo = ConnectionFactory::create([
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => (int)(getenv('DB_PORT') ?: 3306),
                'name' => getenv('DB_NAME') ?: 'fim_dashboard',
                'user' => getenv('DB_USER') ?: 'fim_dashboard',
                'password' => getenv('DB_PASSWORD') ?: '',
            ]);
            $verStmt = $pdo->query('SELECT VERSION()');
            if ($verStmt !== false) {
                $mysqlVersion = (string)$verStmt->fetchColumn();
            }
            $pdoDriverVersion = (string)$pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION);
        } catch (Throwable) {
            // Keep UNAVAILABLE if DB connection is offline
        }

        return [
            'php_version' => $phpVersion,
            'php_sapi' => $phpSapi,
            'opcache_cli_enabled' => $opcacheEnabled,
            'jit_enabled' => $jitEnabled,
            'memory_limit' => $memoryLimit,
            'pdo_mysql_driver_version' => $pdoDriverVersion,
            'mysql_server_version' => $mysqlVersion,
        ];
    }
}
