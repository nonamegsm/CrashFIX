<?php

use yii\db\Migration;

/**
 * Creates session and cache tables. Kept for compatibility with the
 * legacy CrashFix database; not required by the default Yii2 component
 * configuration (which uses file cache + native sessions).
 */
class m250101_000007_init_session_cache extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%YiiSession}}', [
            'id'     => $this->char(40)->notNull(),
            'expire' => $this->integer(),
            'data'   => $this->binary(),
            'PRIMARY KEY (id)' => '',
        ], $tableOptions);

        $this->createTable('{{%cache}}', [
            'id'     => $this->char(128)->notNull(),
            'expire' => $this->integer(),
            'data'   => $this->binary(),
            'PRIMARY KEY (id)' => '',
        ], $tableOptions);
    }

    public function safeDown()
    {
        $this->dropTable('{{%cache}}');
        $this->dropTable('{{%YiiSession}}');
    }
}
