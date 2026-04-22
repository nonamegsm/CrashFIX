<?php

use yii\db\Migration;

/**
 * Creates core CrashFix tables: lookup, usergroup, user, project,
 * appversion, user_project_access.
 */
class m250101_000001_init_core_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%lookup}}', [
            'id'       => $this->primaryKey(),
            'name'     => $this->string(128)->notNull(),
            'code'     => $this->integer()->notNull(),
            'type'     => $this->string(128)->notNull(),
            'position' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_lookup_type_code', '{{%lookup}}', ['type', 'code']);

        $this->createTable('{{%usergroup}}', [
            'id'                          => $this->primaryKey(),
            'name'                        => $this->string(32)->notNull(),
            'description'                 => $this->string(256)->notNull(),
            'status'                      => $this->integer()->notNull(),
            'flags'                       => $this->integer()->notNull(),
            'gperm_access_admin_panel'    => $this->integer()->notNull()->defaultValue(0),
            'pperm_browse_crash_reports'  => $this->integer()->notNull()->defaultValue(0),
            'pperm_browse_bugs'           => $this->integer()->notNull()->defaultValue(0),
            'pperm_browse_debug_info'     => $this->integer()->notNull()->defaultValue(0),
            'pperm_manage_crash_reports'  => $this->integer()->notNull()->defaultValue(0),
            'pperm_manage_bugs'           => $this->integer()->notNull()->defaultValue(0),
            'pperm_manage_debug_info'     => $this->integer()->notNull()->defaultValue(0),
            'default_sidebar_tab'         => $this->string(16)->notNull()->defaultValue('Digest'),
            'default_bug_status_filter'   => $this->string(16)->notNull()->defaultValue('open'),
        ], $tableOptions);

        $this->createTable('{{%user}}', [
            'id'                => $this->primaryKey(),
            'username'          => $this->string(128)->notNull()->unique(),
            'usergroup'         => $this->integer()->notNull(),
            'password'          => $this->string(128)->notNull(),
            'salt'              => $this->string(128)->notNull(),
            'pwd_reset_token'   => $this->string(128),
            'status'            => $this->integer()->notNull(),
            'flags'             => $this->integer()->notNull(),
            'email'             => $this->string(128)->notNull(),
            'cur_project_id'    => $this->integer()->defaultValue(0),
            'cur_appversion_id' => $this->integer()->defaultValue(-1),
        ], $tableOptions);

        $this->createIndex('idx_user_email', '{{%user}}', 'email');
        $this->createIndex('idx_user_usergroup', '{{%user}}', 'usergroup');

        $this->createTable('{{%project}}', [
            'id'                                => $this->primaryKey(),
            'name'                              => $this->string(32)->notNull(),
            'description'                       => $this->string(256),
            'status'                            => $this->integer()->notNull()->defaultValue(1),
            'crash_reports_per_group_quota'     => $this->integer()->notNull()->defaultValue(1000),
            'crash_report_files_disc_quota'     => $this->integer()->notNull()->defaultValue(100),
            'bug_attachment_files_disc_quota'   => $this->integer()->notNull()->defaultValue(100),
            'debug_info_files_disc_quota'       => $this->integer()->notNull()->defaultValue(500),
            'require_exact_build_age'           => $this->integer()->notNull()->defaultValue(0),
        ], $tableOptions);

        $this->createIndex('idx_project_name', '{{%project}}', 'name', true);

        $this->createTable('{{%appversion}}', [
            'id'         => $this->primaryKey(),
            'project_id' => $this->integer()->notNull(),
            'version'    => $this->string(32)->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_appversion_project', '{{%appversion}}', 'project_id');

        $this->createTable('{{%user_project_access}}', [
            'id'           => $this->primaryKey(),
            'user_id'      => $this->integer()->notNull(),
            'project_id'   => $this->integer()->notNull(),
            'usergroup_id' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_upa_user_project', '{{%user_project_access}}', ['user_id', 'project_id'], true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_project_access}}');
        $this->dropTable('{{%appversion}}');
        $this->dropTable('{{%project}}');
        $this->dropTable('{{%user}}');
        $this->dropTable('{{%usergroup}}');
        $this->dropTable('{{%lookup}}');
    }
}
