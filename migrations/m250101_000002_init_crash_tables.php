<?php

use yii\db\Migration;

/**
 * Creates crash-report related tables: crashreport, crashgroup, fileitem,
 * customprop, thread, stackframe, module, processingerror.
 */
class m250101_000002_init_crash_tables extends Migration
{
    public function safeUp()
    {
        $tableOptions = $this->db->driverName === 'mysql'
            ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB'
            : null;

        $this->createTable('{{%crashreport}}', [
            'id'                  => $this->primaryKey(),
            'srcfilename'         => $this->string(512)->notNull(),
            'filesize'            => $this->integer()->notNull(),
            'date_created'        => $this->integer(),
            'received'            => $this->integer()->notNull(),
            'status'              => $this->integer()->notNull(),
            'ipaddress'           => $this->string(32),
            'md5'                 => $this->string(32)->notNull(),
            'groupid'             => $this->integer()->notNull()->defaultValue(0),
            'crashguid'           => $this->string(36),
            'project_id'          => $this->integer()->notNull(),
            'appversion_id'       => $this->integer()->notNull(),
            'emailfrom'           => $this->string(32),
            'description'         => $this->text(),
            'crashrptver'         => $this->string(16),
            'exception_type'      => $this->string(64),
            'exception_code'      => $this->bigInteger(),
            'exception_thread_id' => $this->integer(),
            'exceptionmodule'     => $this->string(512),
            'exceptionmodulebase' => $this->bigInteger(),
            'exceptionaddress'    => $this->bigInteger(),
            'exe_image'           => $this->string(1024),
            'os_name_reg'         => $this->string(512),
            'os_ver_mdmp'         => $this->string(128),
            'os_is_64bit'         => $this->integer(),
            'geo_location'        => $this->string(16),
            'product_type'        => $this->string(128),
            'cpu_architecture'    => $this->string(64),
            'cpu_count'           => $this->integer(),
            'gui_resource_count'  => $this->integer(),
            'open_handle_count'   => $this->integer(),
            'memory_usage_kbytes' => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_crashreport_project', '{{%crashreport}}', 'project_id');
        $this->createIndex('idx_crashreport_group',   '{{%crashreport}}', 'groupid');
        $this->createIndex('idx_crashreport_status',  '{{%crashreport}}', 'status');
        $this->createIndex('idx_crashreport_md5',     '{{%crashreport}}', 'md5');

        $this->createTable('{{%crashgroup}}', [
            'id'            => $this->primaryKey(),
            'created'       => $this->integer()->notNull(),
            'status'        => $this->integer()->notNull(),
            'project_id'    => $this->integer()->notNull(),
            'appversion_id' => $this->integer()->notNull(),
            'title'         => $this->string(256)->notNull(),
            'md5'           => $this->string(32)->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_crashgroup_project', '{{%crashgroup}}', 'project_id');
        $this->createIndex('idx_crashgroup_md5',     '{{%crashgroup}}', 'md5');

        $this->createTable('{{%fileitem}}', [
            'id'             => $this->primaryKey(),
            'crashreport_id' => $this->integer()->notNull(),
            'filename'       => $this->string(512)->notNull(),
            'description'    => $this->string(512),
        ], $tableOptions);

        $this->createIndex('idx_fileitem_crashreport', '{{%fileitem}}', 'crashreport_id');

        $this->createTable('{{%customprop}}', [
            'id'             => $this->primaryKey(),
            'crashreport_id' => $this->integer()->notNull(),
            'name'           => $this->string(128)->notNull(),
            'value'          => $this->text()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx_customprop_crashreport', '{{%customprop}}', 'crashreport_id');

        $this->createTable('{{%thread}}', [
            'id'              => $this->primaryKey(),
            'thread_id'       => $this->integer()->notNull(),
            'crashreport_id'  => $this->integer()->notNull(),
            'stack_trace_md5' => $this->string(32),
        ], $tableOptions);

        $this->createIndex('idx_thread_crashreport', '{{%thread}}', 'crashreport_id');

        $this->createTable('{{%stackframe}}', [
            'id'              => $this->primaryKey(),
            'thread_id'       => $this->integer()->notNull(),
            'addr_pc'         => $this->bigInteger()->notNull(),
            'module_id'       => $this->integer(),
            'offs_in_module'  => $this->integer(),
            'symbol_name'     => $this->string(2048),
            'und_symbol_name' => $this->string(2048),
            'offs_in_symbol'  => $this->integer(),
            'src_file_name'   => $this->string(512),
            'src_line'        => $this->integer(),
            'offs_in_line'    => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_stackframe_thread', '{{%stackframe}}', 'thread_id');

        $this->createTable('{{%module}}', [
            'id'                   => $this->primaryKey(),
            'crashreport_id'       => $this->integer()->notNull(),
            'name'                 => $this->string(512)->notNull(),
            'sym_load_status'      => $this->integer()->notNull(),
            'loaded_debug_info_id' => $this->integer(),
            'file_version'         => $this->string(32)->notNull(),
            'timestamp'            => $this->integer(),
        ], $tableOptions);

        $this->createIndex('idx_module_crashreport', '{{%module}}', 'crashreport_id');

        $this->createTable('{{%processingerror}}', [
            'id'      => $this->primaryKey(),
            'type'    => $this->integer()->notNull(),
            'srcid'   => $this->integer()->notNull(),
            'message' => $this->text(),
        ], $tableOptions);

        $this->createIndex('idx_processingerror_src', '{{%processingerror}}', ['type', 'srcid']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%processingerror}}');
        $this->dropTable('{{%module}}');
        $this->dropTable('{{%stackframe}}');
        $this->dropTable('{{%thread}}');
        $this->dropTable('{{%customprop}}');
        $this->dropTable('{{%fileitem}}');
        $this->dropTable('{{%crashgroup}}');
        $this->dropTable('{{%crashreport}}');
    }
}
