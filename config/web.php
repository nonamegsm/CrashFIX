<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$isInstalled = is_file(__DIR__ . '/user_params.ini') && is_file(__DIR__ . '/installed.txt');

$storageComponent = require __DIR__ . '/storage.php';
$legacyDataAlias = $storageComponent['__legacyAlias'] ?? null;
unset($storageComponent['__legacyAlias']);

$aliases = [
    '@bower' => '@vendor/bower-asset',
    '@npm'   => '@vendor/npm-asset',
];
if ($legacyDataAlias !== null) {
    $aliases['@crashfixLegacyData'] = $legacyDataAlias;
}

$config = [
    'id' => 'CrashFix',
    'name' => 'CrashFix',
    'layout' => 'adminlte/main',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => $aliases,
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '-OIP9zQSewDONvqLhBAxSJDhwKKDOZdx',
        ],
        // Cache lives in the {{%cache}} table during install + in production
        // so multi-server deployments share a single cache surface. Falls
        // back to file cache before install when the DB isn't reachable.
        'cache' => $isInstalled ? [
            'class' => 'yii\caching\DbCache',
            'cacheTable' => '{{%cache}}',
        ] : [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'class' => 'app\components\WebUser',
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'authManager' => $isInstalled ? [
            'class' => 'yii\rbac\DbManager',
            'itemTable' => '{{%AuthItem}}',
            'itemChildTable' => '{{%AuthItemChild}}',
            'assignmentTable' => '{{%AuthAssignment}}',
            'ruleTable' => '{{%AuthRule}}',
        ] : [
            'class' => 'yii\rbac\PhpManager',
        ],
        // Once installed, persist sessions in {{%YiiSession}} so the
        // login state survives PHP-FPM restarts and works across
        // load-balanced web nodes. Pre-install the native PHP handler
        // is fine (and avoids a chicken/egg with the DB).
        'session' => $isInstalled ? [
            'class' => 'yii\web\DbSession',
            'sessionTable' => '{{%YiiSession}}',
            'timeout' => 3600 * 8,
        ] : [
            'class' => 'yii\web\Session',
        ],
        'daemon' => [
            'class' => 'app\components\Daemon',
            'host' => '127.0.0.1',
            'servicePort' => '50',
        ],
        'storage' => $storageComponent,
        'stats' => [
            'class' => 'app\components\Stats',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                // Legacy Yii1 camelCase paths (bookmarks / external links).
                'extraFiles/<action:\w+>/<id:\d+>' => 'extra-files/<action>',
                'extraFiles/<action:\w+>' => 'extra-files/<action>',
                // Static site pages (views/site/pages/{view}.php); must precede generic controller/action rule.
                'site/page/<view:[\w-]+>' => 'site/page',
                '<controller:\w+>/<id:\d+>' => '<controller>/view',
                '<controller:\w+>/<action:\w+>/<id:\d+>' => '<controller>/<action>',
                '<controller:\w+>/<action:\w+>' => '<controller>/<action>',
            ],
        ],
        'assetManager' => [
            'bundles' => [
                'yii\bootstrap4\BootstrapAsset' => [
                    'css' => [], // Disable redundant CSS as AdminLTE includes it
                ],
                // Map Bootstrap 5 to Bootstrap 4 for compatibility
                'yii\bootstrap5\BootstrapAsset' => [
                    'class' => 'yii\bootstrap4\BootstrapAsset',
                    'css' => [],
                ],
                'yii\bootstrap5\BootstrapPluginAsset' => [
                    'class' => 'yii\bootstrap4\BootstrapPluginAsset',
                ],
            ],
        ],
    ],
    'params' => $params,
];


if (YII_ENV_DEV && is_file(__DIR__ . '/user_params.ini')) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
