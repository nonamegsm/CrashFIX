<?php

use yii\db\Migration;

/**
 * Adds format-detection columns to {{%debuginfo}} so the UI can show
 * GCC/DWARF symbol artifacts alongside Microsoft PDB ones.
 *
 * The daemon does not yet populate these columns; they are nullable and
 * the views render NULL as "Unknown" / "detecting...". When the daemon
 * lands its detector (RFC-001, planned for crashfixd 1.0.6-yii2port3)
 * the UI lights up automatically without needing another web-side
 * deploy.
 *
 * Also seeds two new {{%lookup}} rows for the additional terminal
 * status codes that DWARF detection will need:
 *
 *   5  Unsupported format
 *   6  Ready (partial)
 *
 * Both are additive; existing 1..4 status semantics are unchanged.
 */
class m260423_000001_debuginfo_add_format_columns extends Migration
{
    public function safeUp()
    {
        // -------- columns --------
        // High-level format the daemon detected. NULL until the daemon
        // has parsed the file. Expected non-null values:
        //   pdb         - Microsoft PDB
        //   dwarf-elf   - DWARF debug info inside an ELF (so / debug)
        //   dwarf-pe    - DWARF debug info inside a PE (exe / dll)
        //   unknown     - parser ran but could not recognise the file
        $this->addColumn('{{%debuginfo}}', 'format', $this->string(32)->null());

        // Container kind, when applicable: pe / elf / pdb. For DWARF in
        // PE this is "pe"; for a standalone PDB this is "pdb".
        $this->addColumn('{{%debuginfo}}', 'container', $this->string(8)->null());

        // CPU target the symbols describe: x86 / x86_64 / armv7 /
        // aarch64 etc. Free-form so future targets do not need a
        // schema change.
        $this->addColumn('{{%debuginfo}}', 'architecture', $this->string(16)->null());

        // 1 = source line tables present and usable, 0 = stripped or
        // not present, NULL = unknown / not yet parsed.
        $this->addColumn('{{%debuginfo}}', 'has_source_lines', $this->tinyInteger(1)->null());

        // Names the kind of identifier stored in the existing `guid`
        // column, so the detail page can render it as e.g.
        // "PDB GUID+Age: ..." vs "GNU build-id: ...". Expected values:
        //   pdb-guid-age  - Microsoft PDB GUID+Age tuple
        //   gnu-build-id  - DWARF .note.gnu.build-id (SHA1)
        //   pe-guid-age   - PE Debug Directory RSDS GUID+Age
        $this->addColumn('{{%debuginfo}}', 'build_id_kind', $this->string(32)->null());

        // -------- lookup rows --------
        // Use insert() (not insertIgnore()) because if the row already
        // exists the migration is being re-applied and we want a noisy
        // failure rather than silent skip. safeDown removes only the
        // exact (type,code) tuples we added.
        $this->insert('{{%lookup}}', [
            'name'     => 'Unsupported format',
            'type'     => 'DebugInfoStatus',
            'code'     => 5,
            'position' => 5,
        ]);
        $this->insert('{{%lookup}}', [
            'name'     => 'Ready (partial)',
            'type'     => 'DebugInfoStatus',
            'code'     => 6,
            'position' => 6,
        ]);
    }

    public function safeDown()
    {
        $this->delete('{{%lookup}}', ['type' => 'DebugInfoStatus', 'code' => 5]);
        $this->delete('{{%lookup}}', ['type' => 'DebugInfoStatus', 'code' => 6]);

        $this->dropColumn('{{%debuginfo}}', 'build_id_kind');
        $this->dropColumn('{{%debuginfo}}', 'has_source_lines');
        $this->dropColumn('{{%debuginfo}}', 'architecture');
        $this->dropColumn('{{%debuginfo}}', 'container');
        $this->dropColumn('{{%debuginfo}}', 'format');
    }
}
