<?php

namespace app\components;

use yii\db\Connection;

/**
 * Runs mysqldump against the active {@see Connection} for offline migration / backup.
 *
 * Configure an explicit client path when `mysqldump` is not on PATH, e.g. XAMPP:
 * `'mysqldumpPath' => 'C:\\xampp\\mysql\\bin\\mysqldump.exe'` in `config/params.php`.
 */
final class MysqlDumpExporter
{
    /**
     * @return array{ok: bool, exitCode: int, stderr: string}
     */
    public static function dumpToFile(Connection $db, string $outputPath): array
    {
        $parsed = self::parseMysqlDsn((string) $db->dsn);
        if ($parsed['dbname'] === '') {
            return [
                'ok' => false,
                'exitCode' => -1,
                'stderr' => 'Database name is missing from the DSN.',
            ];
        }

        $mysqldump = self::resolveMysqldumpPath();
        $cnf = self::writeClientDefaultsFile($db, $parsed);
        if ($cnf === null) {
            return [
                'ok' => false,
                'exitCode' => -1,
                'stderr' => 'Could not create a temporary MySQL options file.',
            ];
        }

        $parts = [
            $mysqldump,
            '--defaults-extra-file=' . $cnf,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--set-gtid-purged=OFF',
            '--column-statistics=0',
            '--default-character-set=utf8mb4',
            $parsed['dbname'],
        ];

        $cmdLine = '';
        foreach ($parts as $i => $p) {
            $cmdLine .= ($i > 0 ? ' ' : '') . self::shellArg($p);
        }

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['file', $outputPath, 'wb'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmdLine, $spec, $pipes);
        if (!is_resource($proc)) {
            @unlink($cnf);

            return [
                'ok' => false,
                'exitCode' => -1,
                'stderr' => 'Could not start mysqldump. Check mysqldumpPath in params.php or your MySQL client install.',
            ];
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        @unlink($cnf);

        $stderr = is_string($stderr) ? trim($stderr) : '';
        $ok = $exitCode === 0 && is_file($outputPath) && filesize($outputPath) > 0;

        return [
            'ok' => $ok,
            'exitCode' => (int) $exitCode,
            'stderr' => $stderr !== '' ? $stderr : ($ok ? '' : 'mysqldump produced no output or a zero-length file.'),
        ];
    }

    /**
     * @return array{host: string, port: string, dbname: string, socket: ?string}
     */
    private static function parseMysqlDsn(string $dsn): array
    {
        $out = [
            'host' => '127.0.0.1',
            'port' => '3306',
            'dbname' => '',
            'socket' => null,
        ];
        if (stripos($dsn, 'mysql:') !== 0) {
            return $out;
        }
        $tail = substr($dsn, strlen('mysql:'));
        foreach (explode(';', $tail) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2);
            $k = strtolower(trim($k));
            $v = trim($v);
            if ($k === 'host') {
                $out['host'] = $v;
            } elseif ($k === 'dbname' || $k === 'database') {
                $out['dbname'] = $v;
            } elseif ($k === 'port') {
                $out['port'] = $v;
            } elseif ($k === 'unix_socket' || $k === 'socket') {
                $out['socket'] = $v;
            }
        }

        return $out;
    }

    private static function resolveMysqldumpPath(): string
    {
        $params = \Yii::$app->params ?? [];
        $fromParams = $params['mysqldumpPath'] ?? null;
        if (is_string($fromParams) && $fromParams !== '' && is_file($fromParams)) {
            return $fromParams;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            foreach ([
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe',
            ] as $p) {
                if (is_file($p)) {
                    return $p;
                }
            }
        }

        return 'mysqldump';
    }

    /**
     * @param array{host: string, port: string, dbname: string, socket: ?string} $parsed
     */
    private static function writeClientDefaultsFile(Connection $db, array $parsed): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'my_cnf');
        if ($path === false) {
            return null;
        }
        @unlink($path);
        $path .= '.cnf';

        $lines = ["[client]"];
        $lines[] = 'user=' . self::iniEscape((string) $db->username);
        $lines[] = 'password=' . self::iniEscape((string) $db->password);
        if ($parsed['socket'] !== null && $parsed['socket'] !== '') {
            $lines[] = 'socket=' . self::iniEscape($parsed['socket']);
        } else {
            $lines[] = 'host=' . self::iniEscape($parsed['host']);
            $lines[] = 'port=' . self::iniEscape($parsed['port']);
        }

        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            return null;
        }
        @chmod($path, 0600);

        return $path;
    }

    private static function iniEscape(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (preg_match('/[\s"#;\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }

    private static function shellArg(string $arg): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            if ($arg === '') {
                return '""';
            }
            if (preg_match('/^[A-Za-z0-9\\\\:\\._\\/-]+$/', $arg) && strpos($arg, ' ') === false) {
                return $arg;
            }

            return '"' . str_replace(['"', '\\'], ['\\"', '\\\\'], $arg) . '"';
        }

        return escapeshellarg($arg);
    }
}
