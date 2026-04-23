<?php

/**
 * Mirrors the Yii2-port migration of the same name. Adds five nullable
 * format-detection columns to {{debuginfo}} so the legacy admin UI can
 * render PDB / DWARF artefacts side-by-side.
 *
 * The daemon does NOT yet populate these columns; they are nullable
 * and the views render NULL as "Unknown" / "detecting...". When the
 * daemon lands its detector (RFC-001, planned for crashfixd
 * 1.0.6-yii2port3) the UI lights up automatically.
 *
 * NOTE: unlike the Yii2 port, this migration does NOT touch the
 * lookup table. The Yii1 schema already uses DebugInfoStatus codes
 * 5 (PENDING_DELETE) and 6 (DELETE_IN_PROGRESS) for the legacy
 * soft-delete state machine, so the new "Unsupported format" /
 * "Ready (partial)" states cannot be added under those codes here.
 * If the legacy frontend ever needs to surface them it will map
 * them to STATUS_INVALID (4) - informally degraded but functional.
 */
class m260423_000001_debuginfo_add_format_columns extends CDbMigration
{
    public function safeUp()
    {
        $this->addColumn('{{debuginfo}}', 'format',           'VARCHAR(32)  NULL DEFAULT NULL');
        $this->addColumn('{{debuginfo}}', 'container',        'VARCHAR(8)   NULL DEFAULT NULL');
        $this->addColumn('{{debuginfo}}', 'architecture',     'VARCHAR(16)  NULL DEFAULT NULL');
        $this->addColumn('{{debuginfo}}', 'has_source_lines', 'TINYINT(1)   NULL DEFAULT NULL');
        $this->addColumn('{{debuginfo}}', 'build_id_kind',    'VARCHAR(32)  NULL DEFAULT NULL');
    }

    public function safeDown()
    {
        $this->dropColumn('{{debuginfo}}', 'build_id_kind');
        $this->dropColumn('{{debuginfo}}', 'has_source_lines');
        $this->dropColumn('{{debuginfo}}', 'architecture');
        $this->dropColumn('{{debuginfo}}', 'container');
        $this->dropColumn('{{debuginfo}}', 'format');
    }
}
