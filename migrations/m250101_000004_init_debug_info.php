<?php

use yii\db\Migration;

/**
 * Creates the debuginfo table for uploaded debug symbol files.
 */
class m250101_000004_init_debug_info extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%debuginfo}}', [
            'id'           => $this->primaryKey(),
            'project_id'   => $this->integer()->notNull(),
            'dateuploaded' => $this->integer()->notNull(),
            'status'       => $this->integer()->notNull(),
            'filename'     => $this->string(512)->notNull(),
            'guid'         => $this->string(48)->notNull(),
            'md5'          => $this->string(32)->notNull(),
            'filesize'     => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_debuginfo_project', '{{%debuginfo}}', 'project_id');
        $this->createIndex('idx_debuginfo_guid', '{{%debuginfo}}', 'guid');
    }

    public function safeDown()
    {
        $this->dropTable('{{%debuginfo}}');
    }
}
