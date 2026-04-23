<?php

use yii\db\Migration;

/**
 * Extra-files collections: bundles non-standard attachments from crash
 * reports in a date range into one ZIP (legacy tbl_extra_files).
 */
class m260423_200000_init_extra_files extends Migration
{
    public function safeUp()
    {
        if ($this->db->getTableSchema('{{%extra_files}}', true) !== null) {
            return;
        }

        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%extra_files}}', [
            'id'         => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'name'       => $this->string(128)->notNull(),
            'date_from'  => $this->integer(),
            'date_to'    => $this->integer(),
            'status'     => $this->integer()->notNull(),
            // Absolute path may exceed 128 chars on some installs.
            'path'       => $this->string(1024),
        ], $tableOptions);
    }

    public function safeDown()
    {
        $this->dropTable('{{%extra_files}}');
    }
}
