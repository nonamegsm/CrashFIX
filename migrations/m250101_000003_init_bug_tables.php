<?php

use yii\db\Migration;

/**
 * Creates bug tracker tables: bug, bug_change, bug_status_change,
 * bug_attachment, bug_comment, bug_crashreport, bug_crashgroup.
 */
class m250101_000003_init_bug_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%bug}}', [
            'id'                 => $this->primaryKey(),
            'date_created'       => $this->integer()->notNull(),
            'date_last_modified' => $this->integer()->notNull(),
            'date_closed'        => $this->integer(),
            'project_id'         => $this->integer()->notNull(),
            'appversion_id'      => $this->integer()->notNull(),
            'status'             => $this->integer()->notNull(),
            'summary'            => $this->string(256)->notNull(),
            'description'        => $this->text()->notNull(),
            'reported_by'        => $this->integer()->notNull(),
            'assigned_to'        => $this->integer(),
            'priority'           => $this->integer()->notNull(),
            'reproducability'    => $this->integer()->notNull(),
            'merged_into'        => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_bug_project', '{{%bug}}', 'project_id');
        $this->createIndex('idx_bug_status', '{{%bug}}', 'status');
        $this->createIndex('idx_bug_assigned', '{{%bug}}', 'assigned_to');

        $this->createTable('{{%bug_change}}', [
            'id'               => $this->primaryKey(),
            'bug_id'           => $this->integer()->notNull(),
            'timestamp'        => $this->integer()->notNull(),
            'user_id'          => $this->integer()->notNull(),
            'flags'            => $this->integer()->notNull(),
            'status_change_id' => $this->integer(),
            'comment_id'       => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_bug_change_bug', '{{%bug_change}}', 'bug_id');

        $this->createTable('{{%bug_status_change}}', [
            'id'              => $this->primaryKey(),
            'status'          => $this->integer(),
            'assigned_to'     => $this->integer(),
            'priority'        => $this->integer(),
            'reproducability' => $this->integer(),
            'merged_into'     => $this->integer(),
        ], $tableOptions);

        $this->createTable('{{%bug_attachment}}', [
            'id'            => $this->primaryKey(),
            'bug_change_id' => $this->integer()->notNull(),
            'filename'      => $this->string(512)->notNull(),
            'filesize'      => $this->integer()->notNull(),
            'md5'           => $this->string(32)->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_bug_attachment_change', '{{%bug_attachment}}', 'bug_change_id');

        $this->createTable('{{%bug_comment}}', [
            'id'   => $this->primaryKey(),
            'text' => $this->text(),
        ], $tableOptions);

        $this->createTable('{{%bug_crashreport}}', [
            'id'             => $this->primaryKey(),
            'bug_id'         => $this->integer()->notNull(),
            'crashreport_id' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_bug_crashreport', '{{%bug_crashreport}}', ['bug_id', 'crashreport_id'], true);

        $this->createTable('{{%bug_crashgroup}}', [
            'id'            => $this->primaryKey(),
            'bug_id'        => $this->integer()->notNull(),
            'crashgroup_id' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_bug_crashgroup', '{{%bug_crashgroup}}', ['bug_id', 'crashgroup_id'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%bug_crashgroup}}');
        $this->dropTable('{{%bug_crashreport}}');
        $this->dropTable('{{%bug_comment}}');
        $this->dropTable('{{%bug_attachment}}');
        $this->dropTable('{{%bug_status_change}}');
        $this->dropTable('{{%bug_change}}');
        $this->dropTable('{{%bug}}');
    }
}
