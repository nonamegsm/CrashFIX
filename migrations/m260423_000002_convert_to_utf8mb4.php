<?php

use yii\db\Migration;

/**
 * Defensively convert every CrashFix table to utf8mb4.
 *
 * The Yii2 port's own init migrations (m250101_*) already specify
 * CHARACTER SET utf8mb4 explicitly, so on a clean Yii2 install this
 * migration is a no-op for the schema itself - all it does is set
 * the database-level default so any future migration without an
 * explicit charset still gets utf8mb4.
 *
 * However: the Yii2 port is intended to run against the SAME
 * database the legacy Yii1 frontend uses (so both UIs see the same
 * data). When the DB was originally created by the Yii1 installer
 * (back in 2012-era CrashFix), tables were created with the MySQL
 * server default charset - typically latin1 - and storing Cyrillic /
 * CJK / emoji into tbl_customprop fails with:
 *
 *   SQLSTATE[22007]: Incorrect string value: '\xD0\xA4...' for
 *   column 'crashfix.tbl_customprop.name'
 *
 * This migration is the same idempotent walker shipped to the Yii1
 * frontend on the php8-compat branch (m260423_000002_convert_to_utf8mb4),
 * lifted into Yii2-port migration shape.
 */
class m260423_000002_convert_to_utf8mb4 extends Migration
{
    public function safeUp()
    {
        // Only meaningful on MySQL / MariaDB; quietly skip on SQLite,
        // PostgreSQL, etc. so the test harness on those drivers does
        // not blow up.
        if ($this->db->driverName !== 'mysql') {
            echo "  driver is {$this->db->driverName}, skipping (utf8mb4 is a MySQL concept)\n";
            return;
        }

        $dbName = (string) $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $prefix = (string) $this->db->tablePrefix; // typically 'tbl_'
        $target = 'utf8mb4';
        $coll   = 'utf8mb4_unicode_ci';

        echo "  database: {$dbName}, table prefix: '{$prefix}', target charset: {$target}\n";

        $this->execute(
            "ALTER DATABASE `{$dbName}` CHARACTER SET = {$target} COLLATE = {$coll}"
        );

        $sql = "
            SELECT t.TABLE_NAME, ccsa.CHARACTER_SET_NAME
              FROM information_schema.TABLES t
              JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
                ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
             WHERE t.TABLE_SCHEMA = :db
               AND t.TABLE_TYPE   = 'BASE TABLE'
               AND t.TABLE_NAME LIKE :pfx
        ";
        $rows = $this->db->createCommand($sql, [
            ':db'  => $dbName,
            ':pfx' => $prefix . '%',
        ])->queryAll();

        $converted = 0;
        $skipped   = 0;
        foreach ($rows as $r) {
            if ($r['CHARACTER_SET_NAME'] === $target) {
                $skipped++;
                continue;
            }
            echo "    converting {$r['TABLE_NAME']} ({$r['CHARACTER_SET_NAME']} -> {$target})\n";
            $this->execute(
                "ALTER TABLE `{$r['TABLE_NAME']}`
                    CONVERT TO CHARACTER SET {$target} COLLATE {$coll}"
            );
            $converted++;
        }

        echo "  done: {$converted} converted, {$skipped} already-utf8mb4\n";
    }

    public function safeDown()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        // Reset the database default only; do NOT round-trip data
        // back to latin1 because that would truncate real
        // Cyrillic / CJK / emoji content that this migration's up()
        // accepted. Same rationale as the Yii1 sibling.
        $dbName = (string) $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->execute(
            "ALTER DATABASE `{$dbName}` CHARACTER SET = latin1 COLLATE = latin1_swedish_ci"
        );
        echo "  database default reverted to latin1; per-table data left as utf8mb4\n";
    }
}
