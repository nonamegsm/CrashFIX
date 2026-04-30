<?php

return [
    /** CrashFix web application version — keep aligned with crashfixd `LIBDUMPER_VER` / Debian package. */
    'version' => '1.0.10',

    /** Optional: full path to mysqldump for Admin → Yii1 migration export (see MysqlDumpExporter). */
    'mysqldumpPath' => null,

    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
];
