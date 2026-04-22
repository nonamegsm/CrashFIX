<?php

if (!is_file(__DIR__ . '/user_params.ini')) {
    // Default values if no INI file is found
    $userParams = [
        'db_connection_string' => 'mysql:host=127.0.0.1;dbname=crashfix',
        'db_username' => 'crashfix',
        'db_password' => '',
        'db_table_prefix' => 'tbl_',
    ];
} else {
    $userParams = @parse_ini_file(__DIR__ . '/user_params.ini');
    if ($userParams === false) {
        $userParams = [];
    }
}

return [
    'class' => 'yii\db\Connection',
    'dsn' => str_replace('%DATA_DIR%', __DIR__ . '/../data', $userParams['db_connection_string'] ?? 'mysql:host=127.0.0.1;dbname=crashfix'),
    'username' => $userParams['db_username'] ?? 'crashfix',
    'password' => $userParams['db_password'] ?? '',
    'charset' => 'utf8',
    'tablePrefix' => $userParams['db_table_prefix'] ?? 'tbl_',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
