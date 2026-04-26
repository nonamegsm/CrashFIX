<?php

$yiiDebug = $_SERVER['YII_DEBUG'] ?? getenv('YII_DEBUG');
$yiiEnv = $_SERVER['YII_ENV'] ?? getenv('YII_ENV');

defined('YII_DEBUG') or define(
    'YII_DEBUG',
    filter_var($yiiDebug, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true
);
defined('YII_ENV') or define('YII_ENV', $yiiEnv !== false && $yiiEnv !== null && $yiiEnv !== '' ? $yiiEnv : 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
