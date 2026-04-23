<?php

/**
 * Web config for Codeception (functional / some unit tests).
 *
 * Based on web.php so routing, layouts, and controllers match production,
 * with DB and security overrides. Requires MySQL database `crashfix_test`
 * (see config/test_db.php).
 */
$config = require __DIR__ . '/web.php';

$config['id'] = 'CrashFix-test';

$config['components']['db'] = require __DIR__ . '/test_db.php';

$config['components']['request'] = array_merge(
    $config['components']['request'] ?? [],
    [
        'cookieValidationKey' => 'test-functional-cookie-key',
        'enableCsrfValidation' => false,
    ]
);

$config['components']['cache'] = ['class' => 'yii\caching\FileCache'];
$config['components']['session'] = ['class' => 'yii\web\Session'];
$config['components']['authManager'] = ['class' => 'yii\rbac\PhpManager'];

$config['components']['storage'] = [
    'class' => 'app\components\Storage',
    'basePath' => '@runtime/test-storage',
];

$config['components']['daemon'] = [
    'class' => 'app\components\Daemon',
    'host' => '127.0.0.1',
    'servicePort' => 65530,
    'timeout' => 1,
];

$config['components']['mailer'] = array_merge(
    $config['components']['mailer'] ?? [],
    [
        'useFileTransport' => true,
    ]
);

// Predictable ?r=controller/action URLs in tests (no pretty rules).
$config['components']['urlManager'] = [
    'enablePrettyUrl' => false,
    'showScriptName' => true,
];

return $config;
