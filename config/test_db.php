<?php

/**
 * Database configuration for the test suite.
 *
 * Deliberately separated from db.php so unit/functional/acceptance
 * tests never touch the production schema. The connection points at
 * `crashfix_test`, which is created and migrated by `composer test`
 * (or manually via `php yii migrate --interactive=0` after pointing
 * the env at the test DB).
 */
return [
    'class'        => 'yii\db\Connection',
    'dsn'          => 'mysql:host=127.0.0.1;dbname=crashfix_test',
    'username'     => 'root',
    'password'     => '',
    'charset'      => 'utf8mb4',
    'tablePrefix'  => 'tbl_',
];
