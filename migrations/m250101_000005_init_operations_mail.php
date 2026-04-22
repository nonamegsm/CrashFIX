<?php

use yii\db\Migration;

/**
 * Creates daemon operations log and mail queue tables.
 */
class m250101_000005_init_operations_mail extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%operation}}', [
            'id'        => $this->primaryKey(),
            'status'    => $this->integer()->notNull(),
            'timestamp' => $this->integer()->notNull(),
            'optype'    => $this->integer()->notNull(),
            'srcid'     => $this->integer()->notNull(),
            'cmdid'     => $this->string(32)->notNull(),
            'operand1'  => $this->text(),
            'operand2'  => $this->text(),
            'operand3'  => $this->text(),
        ], $tableOptions);

        $this->createIndex('idx_operation_timestamp', '{{%operation}}', 'timestamp');
        $this->createIndex('idx_operation_status', '{{%operation}}', 'status');

        $this->createTable('{{%mail_queue}}', [
            'id'            => $this->primaryKey(),
            'create_time'   => $this->integer()->notNull(),
            'status'        => $this->integer()->notNull(),
            'sent_time'     => $this->integer(),
            'recipient'     => $this->string(1024)->notNull(),
            'email_subject' => $this->string(256)->notNull(),
            'email_headers' => $this->string(1024)->notNull(),
            'email_body'    => $this->text()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_mail_queue_status', '{{%mail_queue}}', 'status');
    }

    public function safeDown()
    {
        $this->dropTable('{{%mail_queue}}');
        $this->dropTable('{{%operation}}');
    }
}
