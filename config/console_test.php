<?php

/**
 * Console config for migrating / maintaining the test database only.
 *
 * Usage:
 *   php yii migrate --appconfig=config/console_test.php --interactive=0
 */
$config = require __DIR__ . '/console.php';
$config['components']['db'] = require __DIR__ . '/test_db.php';

return $config;
