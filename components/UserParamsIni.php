<?php

namespace app\components;

/**
 * Read/write {@see config/user_params.ini} with optional [storage] section.
 *
 * Flat parse (no process_sections) merges keys from all sections; storage
 * keys must use a `storage_` prefix so they never collide with db_* keys.
 */
final class UserParamsIni
{
    public const STORAGE_LAYOUT_PROJECT = 'project_scoped';
    public const STORAGE_LAYOUT_LEGACY = 'legacy_yii1';

    /**
     * @return array<string, string>
     */
    public static function readFlat(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $parsed = @parse_ini_file($path);
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<string, string> $dbParams   keys: db_connection_string, db_username, …
     * @param array<string, string> $storageParams keys: storage_layout, storage_base_path (optional),
     *        installer_profile (optional, fresh|existing_yii1 — written by the web installer)
     */
    public static function write(string $path, array $dbParams, array $storageParams = []): void
    {
        $lines = ['[db]'];
        foreach ($dbParams as $key => $value) {
            $lines[] = self::iniLine($key, (string) $value);
        }
        if ($storageParams !== []) {
            $lines[] = '';
            $lines[] = '[storage]';
            foreach ($storageParams as $key => $value) {
                $lines[] = self::iniLine($key, (string) $value);
            }
        }
        $lines[] = '';
        file_put_contents($path, implode(PHP_EOL, $lines));
    }

    private static function iniLine(string $key, string $value): string
    {
        $escaped = str_replace('"', '\"', $value);

        return sprintf('%s = "%s"', $key, $escaped);
    }
}
