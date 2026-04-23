<?php

/**
 * Database configuration for the test suite.
 *
 * Deliberately separated from db.php so unit/functional/acceptance
 * tests never touch the production schema. The connection points at
 * `crashfix_test`. Create the empty database, then run migrations with
 * `php yii migrate --appconfig=config/console_test.php --interactive=0`.
 */
return [
    'class'        => 'yii\db\Connection',
    'dsn'          => 'mysql:host=127.0.0.1;dbname=crashfix_test',
    'username'     => 'root',
    'password'     => '',
    'charset'      => 'utf8mb4',
    'tablePrefix'  => 'tbl_',
];
