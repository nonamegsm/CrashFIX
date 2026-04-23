<?php

use yii\db\Migration;

/**
 * Creates the read-only SQL view `view_serials_report_count` used by
 * the Serials Info admin page. Mirror of the Yii1 migration
 * m240519_110139_create_view_serials_report_count from the legacy
 * php8-compat branch.
 *
 * The view aggregates pairs of CrashRpt-side custom properties named
 * 'Box Serial' and 'Card Serial' (a project-specific convention used
 * by CrashFix's hardware customers - SmartJTAGBox and similar) and
 * counts how many distinct crash reports each (box, card) pair has
 * generated. Used by support to identify hardware units that crash
 * disproportionately often.
 *
 * If your application doesn't send those custom-prop names the view
 * simply returns zero rows - it's harmless on installs that don't
 * use this convention.
 *
 * Notes
 * -----
 * * The view name is intentionally NOT prefixed with `tbl_` because
 *   the original Yii1 install hard-coded the name; the Yii1 frontend
 *   queries it as `view_serials_report_count` directly. Keeping the
 *   same name lets both frontends share a single DB.
 * * Underlying table names are referenced with the configured table
 *   prefix so multi-prefix installs (rare) still work.
 * * Driver-guarded: skipped on non-MySQL since CREATE VIEW syntax
 *   varies across drivers and SQLite doesn't support our GROUP BY
 *   pattern cleanly.
 */
class m260423_000003_create_view_serials_report_count extends Migration
{
    public function safeUp()
    {
        if ($this->db->driverName !== 'mysql') {
            echo "  driver is {$this->db->driverName}, skipping (view DDL is MySQL-specific)\n";
            return;
        }

        $prefix = (string) $this->db->tablePrefix; // typically 'tbl_'
        $cp     = "`{$prefix}customprop`";
        $cr     = "`{$prefix}crashreport`";
        $view   = '`view_serials_report_count`';

        // Drop first so this migration can be re-applied cleanly on
        // installs that already had the Yii1 version of the view.
        $this->execute("DROP VIEW IF EXISTS {$view}");

        $this->execute("
            CREATE VIEW {$view} AS
            SELECT
                box.`value`  AS `box_serial`,
                card.`value` AS `card_serial`,
                COUNT(DISTINCT cr.`id`) AS `report_count`
            FROM {$cp} box
            JOIN {$cp} card ON box.`crashreport_id` = card.`crashreport_id`
            JOIN {$cr} cr   ON box.`crashreport_id` = cr.`id`
            WHERE box.`name`  = 'Box Serial'
              AND card.`name` = 'Card Serial'
            GROUP BY box.`value`, card.`value`
        ");
    }

    public function safeDown()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        $this->execute("DROP VIEW IF EXISTS `view_serials_report_count`");
    }
}
