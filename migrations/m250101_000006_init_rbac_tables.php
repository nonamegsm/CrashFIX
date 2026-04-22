<?php

use yii\db\Migration;

/**
 * Creates Yii RBAC tables under the legacy CrashFix names that the
 * application's authManager configuration is wired to.
 *
 * NOTE: We deliberately use the legacy CrashFix table names
 * (AuthItem, AuthItemChild, AuthAssignment, AuthRule) instead of the
 * Yii2 default (auth_item, auth_item_child, auth_assignment, auth_rule)
 * to stay backwards compatible with existing legacy CrashFix databases.
 */
class m250101_000006_init_rbac_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%AuthItem}}', [
            'name'        => $this->string(64)->notNull(),
            'type'        => $this->integer()->notNull(),
            'description' => $this->text(),
            'bizrule'     => $this->text(),
            'data'        => $this->text(),
            'PRIMARY KEY (name)' => '',
        ], $tableOptions);

        $this->createTable('{{%AuthItemChild}}', [
            'parent' => $this->string(64)->notNull(),
            'child'  => $this->string(64)->notNull(),
            'PRIMARY KEY (parent, child)' => '',
        ], $tableOptions);

        $this->addForeignKey('fk_authitem_parent', '{{%AuthItemChild}}', 'parent', '{{%AuthItem}}', 'name', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_authitem_child',  '{{%AuthItemChild}}', 'child',  '{{%AuthItem}}', 'name', 'CASCADE', 'CASCADE');

        $this->createTable('{{%AuthAssignment}}', [
            'itemname' => $this->string(64)->notNull(),
            'user_id'  => $this->string(64)->notNull(),
            'bizrule'  => $this->text(),
            'data'     => $this->text(),
            'PRIMARY KEY (itemname, user_id)' => '',
        ], $tableOptions);

        $this->addForeignKey('fk_authassignment_item', '{{%AuthAssignment}}', 'itemname', '{{%AuthItem}}', 'name', 'CASCADE', 'CASCADE');

        $this->createTable('{{%AuthRule}}', [
            'name'       => $this->string(64)->notNull(),
            'data'       => $this->binary(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
            'PRIMARY KEY (name)' => '',
        ], $tableOptions);
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_authassignment_item', '{{%AuthAssignment}}');
        $this->dropForeignKey('fk_authitem_child', '{{%AuthItemChild}}');
        $this->dropForeignKey('fk_authitem_parent', '{{%AuthItemChild}}');
        $this->dropTable('{{%AuthRule}}');
        $this->dropTable('{{%AuthAssignment}}');
        $this->dropTable('{{%AuthItemChild}}');
        $this->dropTable('{{%AuthItem}}');
    }
}
