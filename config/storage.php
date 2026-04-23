<?php

// Included before the Application registers the `app\` Composer autoload path.
require_once __DIR__ . '/../components/UserParamsIni.php';

use app\components\LegacyStorage;
use app\components\Storage;
use app\components\UserParamsIni;

/**
 * Storage component definition from user_params.ini (after install) or defaults.
 *
 * Legacy layout expects {@see LegacyStorage} with an absolute path to the Yii1
 * `protected/data` directory (the folder that contains `crashReports/`, `debugInfo/`, …).
 */
$iniPath = __DIR__ . '/user_params.ini';
$ini = UserParamsIni::readFlat($iniPath);
$layout = $ini['storage_layout'] ?? UserParamsIni::STORAGE_LAYOUT_PROJECT;
$legacyPath = trim((string) ($ini['storage_base_path'] ?? ''));

if ($layout === UserParamsIni::STORAGE_LAYOUT_LEGACY && $legacyPath !== '') {
    $normalized = rtrim(str_replace('\\', '/', $legacyPath), '/');

    return [
        'class' => LegacyStorage::class,
        'basePath' => '@crashfixLegacyData',
        '__legacyAlias' => $normalized,
    ];
}

return [
    'class' => Storage::class,
    'basePath' => '@app/data',
    '__legacyAlias' => null,
];
