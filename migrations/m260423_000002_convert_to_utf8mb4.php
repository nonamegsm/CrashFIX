<?php

use yii\db\Migration;

/**
 * Defensively convert every CrashFix table to utf8mb4 via the
 * drop-FKs / convert / re-add-FKs dance.
 *
 * SET FOREIGN_KEY_CHECKS=0 does NOT bypass error 1832 ("Cannot
 * change column 'X': used in a foreign key constraint 'Y'") because
 * that's enforced by InnoDB structural metadata, not the runtime
 * referential-integrity check that the flag controls. The only
 * documented workaround is to drop every FK, do the charset walk,
 * and recreate every FK from a snapshot.
 *
 * The Yii2 port's own init migrations (m250101_*) already specify
 * CHARACTER SET utf8mb4 explicitly, so on a clean Yii2 install this
 * migration is a no-op for the schema itself - all it does is set
 * the database-level default. But on shared-DB deployments where
 * the legacy Yii1 frontend created the schema first, the walk is
 * the real fix and is mirrored from the Yii1 sibling on the
 * php8-compat branch.
 */
class m260423_000002_convert_to_utf8mb4 extends Migration
{
    public function safeUp()
    {
        if ($this->db->driverName !== 'mysql') {
            echo "  driver is {$this->db->driverName}, skipping (utf8mb4 is a MySQL concept)\n";
            return;
        }

        $dbName = (string) $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $prefix = (string) $this->db->tablePrefix;
        $target = 'utf8mb4';
        $coll   = 'utf8mb4_unicode_ci';

        echo "  database: {$dbName}, table prefix: '{$prefix}', target charset: {$target}\n";

        // ----- 1. Snapshot every FK in the schema --------------------
        $fkRows = $this->db->createCommand("
            SELECT
                rc.CONSTRAINT_NAME       AS NAME,
                rc.TABLE_NAME            AS TBL,
                rc.REFERENCED_TABLE_NAME AS REF_TBL,
                rc.UPDATE_RULE           AS ON_UPDATE,
                rc.DELETE_RULE           AS ON_DELETE,
                GROUP_CONCAT(kcu.COLUMN_NAME            ORDER BY kcu.ORDINAL_POSITION) AS COLS,
                GROUP_CONCAT(kcu.REFERENCED_COLUMN_NAME ORDER BY kcu.ORDINAL_POSITION) AS REF_COLS
              FROM information_schema.REFERENTIAL_CONSTRAINTS rc
              JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
               AND kcu.CONSTRAINT_NAME   = rc.CONSTRAINT_NAME
               AND kcu.TABLE_NAME        = rc.TABLE_NAME
             WHERE rc.CONSTRAINT_SCHEMA = :db
               AND rc.TABLE_NAME LIKE :pfx
             GROUP BY rc.CONSTRAINT_NAME, rc.TABLE_NAME,
                      rc.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE
        ", [
            ':db'  => $dbName,
            ':pfx' => $prefix . '%',
        ])->queryAll();

        echo "  found " . count($fkRows) . " foreign-key constraint(s)\n";

        // ----- 2. ALTER DATABASE default charset --------------------
        $this->execute(
            "ALTER DATABASE `{$dbName}` CHARACTER SET = {$target} COLLATE = {$coll}"
        );

        // ----- 3. Drop every FK ------------------------------------
        foreach ($fkRows as $fk) {
            echo "    dropping FK {$fk['NAME']} on {$fk['TBL']}\n";
            try {
                $this->execute(
                    "ALTER TABLE `{$fk['TBL']}` DROP FOREIGN KEY `{$fk['NAME']}`"
                );
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (strpos($msg, "Can't DROP") === false &&
                    strpos($msg, '1091')      === false) {
                    throw $e;
                }
                echo "      (already dropped, continuing)\n";
            }
        }

        // ----- 4. The big walk: convert every table ----------------
        $convertEx = null;
        try {
            $rows = $this->db->createCommand("
                SELECT t.TABLE_NAME, ccsa.CHARACTER_SET_NAME
                  FROM information_schema.TABLES t
                  JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
                    ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
                 WHERE t.TABLE_SCHEMA = :db
                   AND t.TABLE_TYPE   = 'BASE TABLE'
                   AND t.TABLE_NAME LIKE :pfx
            ", [
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
        } catch (\Throwable $e) {
            $convertEx = $e;
            echo "  WARNING: convert pass threw: " . $e->getMessage() . "\n";
            echo "  attempting to restore FKs anyway...\n";
        }

        // ----- 5. Recreate every FK from the snapshot --------------
        $existingFks = $this->db->createCommand("
            SELECT CONSTRAINT_NAME AS NAME, TABLE_NAME AS TBL
              FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :db
        ", [':db' => $dbName])->queryAll();

        $existingSet = [];
        foreach ($existingFks as $row) {
            $existingSet[$row['TBL'] . '/' . $row['NAME']] = true;
        }

        $recreated   = 0;
        $alreadyHere = 0;
        foreach ($fkRows as $fk) {
            $key = $fk['TBL'] . '/' . $fk['NAME'];
            if (isset($existingSet[$key])) {
                $alreadyHere++;
                continue;
            }
            $cols    = '`' . str_replace(',', '`,`', $fk['COLS'])     . '`';
            $refCols = '`' . str_replace(',', '`,`', $fk['REF_COLS']) . '`';
            $sql = "ALTER TABLE `{$fk['TBL']}`
                      ADD CONSTRAINT `{$fk['NAME']}`
                      FOREIGN KEY ({$cols})
                      REFERENCES `{$fk['REF_TBL']}` ({$refCols})
                      ON UPDATE {$fk['ON_UPDATE']}
                      ON DELETE {$fk['ON_DELETE']}";
            try {
                $this->execute($sql);
                echo "    recreated FK {$fk['NAME']} on {$fk['TBL']}\n";
                $recreated++;
            } catch (\Throwable $e) {
                echo "    WARNING: failed to recreate FK {$fk['NAME']} on {$fk['TBL']}: "
                     . $e->getMessage() . "\n";
            }
        }
        echo "  FK summary: {$recreated} recreated, {$alreadyHere} already-present\n";

        if ($convertEx !== null) {
            throw $convertEx;
        }
    }

    public function safeDown()
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }
        $dbName = (string) $this->db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->execute(
            "ALTER DATABASE `{$dbName}` CHARACTER SET = latin1 COLLATE = latin1_swedish_ci"
        );
        echo "  database default reverted to latin1; per-table data left as utf8mb4\n";
    }
}
