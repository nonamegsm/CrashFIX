<?php

use yii\db\Migration;

/**
 * Block 5 (Poll parity): debug-info soft-delete pipeline used statuses 5/6 in
 * Yii1; those codes are DWARF states in Yii2, so deletion uses 7/8.
 *
 * Also adds tbl_module.matching_pdb_guid populated from daemon XML import.
 */
class m260424_000001_poll_debuginfo_delete_status_and_module_guid extends Migration
{
    public function safeUp()
    {
        $this->insert('{{%lookup}}', [
            'name'     => 'Pending Delete',
            'type'     => 'DebugInfoStatus',
            'code'     => 7,
            'position' => 7,
        ]);
        $this->insert('{{%lookup}}', [
            'name'     => 'Deleting',
            'type'     => 'DebugInfoStatus',
            'code'     => 8,
            'position' => 8,
        ]);

        if ($this->db->getTableSchema('{{%module}}', true)->getColumn('matching_pdb_guid') === null) {
            $this->addColumn('{{%module}}', 'matching_pdb_guid', $this->string(256)->null());
        }
    }

    public function safeDown()
    {
        $this->delete('{{%lookup}}', ['type' => 'DebugInfoStatus', 'code' => 7]);
        $this->delete('{{%lookup}}', ['type' => 'DebugInfoStatus', 'code' => 8]);

        if ($this->db->getTableSchema('{{%module}}', true)->getColumn('matching_pdb_guid') !== null) {
            $this->dropColumn('{{%module}}', 'matching_pdb_guid');
        }
    }
}
