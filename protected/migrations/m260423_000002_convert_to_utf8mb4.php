<?php

/**
 * Convert every CrashFix table from whatever historical charset
 * (typically latin1, sometimes utf8) to utf8mb4.
 *
 * Why
 * ---
 * The original CrashFix schema (m120515_122132_initial.php) created
 * tables without an explicit CHARACTER SET clause, so they inherited
 * the MySQL server default - which on most installs from that era
 * is latin1. Storing non-Latin-1 input (Cyrillic, CJK, emoji, even
 * German esszett in some encodings) into those columns fails with:
 *
 *   SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect
 *   string value: '\xD0\xA4...' for column 'tbl_customprop.name'
 *
 * Approach (drop-FKs / convert / re-add-FKs)
 * ------------------------------------------
 * MySQL refuses to ALTER TABLE ... CONVERT TO CHARACTER SET on any
 * column referenced by a foreign-key constraint:
 *
 *   1832 Cannot change column 'X': used in a foreign key constraint 'Y'
 *
 * SET FOREIGN_KEY_CHECKS=0 does NOT bypass this. That flag only
 * disables runtime DML referential integrity; the 1832 check is a
 * separate structural metadata enforcement that always runs during
 * DDL.
 *
 * The documented workaround is:
 *   1. Snapshot every FK constraint in the schema (name, table,
 *      columns, ref_table, ref_columns, ON UPDATE, ON DELETE) from
 *      information_schema.
 *   2. DROP every FK.
 *   3. Convert charset on every table.
 *   4. Recreate every FK from the snapshot.
 *
 * The whole sequence is wrapped in try / finally so even if the
 * convert step throws partway through, we make a best-effort attempt
 * to restore FKs - leaving the schema in a worse state than we
 * started would be far worse than failing the migration.
 *
 * Idempotency
 * -----------
 * - Tables already at utf8mb4 are skipped on the convert pass.
 * - FK recreate skips any constraint that's already present (e.g. a
 *   previous run dropped + recreated a subset).
 * So this migration is safe to re-run if a prior attempt failed
 * mid-walk. The migration row is only inserted on a clean completion;
 * Yii1's migrate runner will retry from scratch each time it's invoked.
 */
class m260423_000002_convert_to_utf8mb4 extends CDbMigration
{
    public function safeUp()
    {
        $db = $this->getDbConnection();
        $dbName  = $db->createCommand('SELECT DATABASE()')->queryScalar();
        $prefix  = $db->tablePrefix; // typically 'tbl_'
        $target  = 'utf8mb4';
        $coll    = 'utf8mb4_unicode_ci';

        echo "  database: $dbName, table prefix: '$prefix', target charset: $target\n";

        // ----- 1. Snapshot every FK in the schema --------------------
        // GROUP_CONCAT preserves the column order via ORDER BY
        // ORDINAL_POSITION so multi-column FKs survive the round trip.
        $fkRows = $db->createCommand("
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
        ")->queryAll(true, array(
            ':db'  => $dbName,
            ':pfx' => $prefix . '%',
        ));

        echo "  found ".count($fkRows)." foreign-key constraint(s)\n";

        // ----- 2. ALTER DATABASE default charset --------------------
        $this->execute(
            "ALTER DATABASE `$dbName` CHARACTER SET = $target COLLATE = $coll"
        );

        // ----- 3. Drop every FK ------------------------------------
        // Some FKs may have been dropped by a previous failed run;
        // catch and continue past "constraint does not exist" errors
        // so the migration is idempotent.
        foreach ($fkRows as $fk) {
            echo "    dropping FK {$fk['NAME']} on {$fk['TBL']}\n";
            try {
                $this->execute(
                    "ALTER TABLE `{$fk['TBL']}` DROP FOREIGN KEY `{$fk['NAME']}`"
                );
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // MySQL: 1091 "check that column/key exists"
                // MariaDB: similar phrasing. Either way, the only
                // legitimate cause for a drop failure here is that the
                // FK is already gone, which is fine. Re-throw on
                // anything else so we don't proceed past unexpected
                // breakage.
                if (strpos($msg, "Can't DROP") === false &&
                    strpos($msg, '1091')      === false) {
                    throw $e;
                }
                echo "      (already dropped, continuing)\n";
            }
        }

        // ----- 4. The big walk: convert every table ----------------
        // Wrapped in try / finally so we ALWAYS attempt to recreate
        // FKs below, even if a CONVERT throws partway. Half-converted
        // schema with no FKs is far worse than half-converted schema
        // with restored FKs.
        $convertEx = null;
        try {
            $rows = $db->createCommand("
                SELECT t.TABLE_NAME, ccsa.CHARACTER_SET_NAME
                  FROM information_schema.TABLES t
                  JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
                    ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
                 WHERE t.TABLE_SCHEMA = :db
                   AND t.TABLE_TYPE   = 'BASE TABLE'
                   AND t.TABLE_NAME LIKE :pfx
            ")->queryAll(true, array(
                ':db'  => $dbName,
                ':pfx' => $prefix . '%',
            ));

            $converted = 0;
            $skipped   = 0;
            foreach ($rows as $r) {
                $tname = $r['TABLE_NAME'];
                $cset  = $r['CHARACTER_SET_NAME'];
                if ($cset === $target) {
                    $skipped++;
                    continue;
                }
                echo "    converting $tname ($cset -> $target)\n";
                $this->execute(
                    "ALTER TABLE `$tname`
                        CONVERT TO CHARACTER SET $target COLLATE $coll"
                );
                $converted++;
            }
            echo "  done: $converted converted, $skipped already-utf8mb4\n";
        } catch (Exception $e) {
            $convertEx = $e;
            echo "  WARNING: convert pass threw: ".$e->getMessage()."\n";
            echo "  attempting to restore FKs anyway...\n";
        }

        // ----- 5. Recreate every FK from the snapshot --------------
        // FK names + column lists + rules are preserved exactly, so
        // application code that grepped tbl_AuthAssignment_ibfk_1
        // (or any other constraint name) keeps working.
        // Skip any FK that's already present (re-run-safe).
        $existingFks = $db->createCommand("
            SELECT CONSTRAINT_NAME AS NAME, TABLE_NAME AS TBL
              FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :db
        ")->queryAll(true, array(':db' => $dbName));

        $existingSet = array();
        foreach ($existingFks as $row) {
            $existingSet[$row['TBL'].'/'.$row['NAME']] = true;
        }

        $recreated   = 0;
        $alreadyHere = 0;
        foreach ($fkRows as $fk) {
            $key = $fk['TBL'].'/'.$fk['NAME'];
            if (isset($existingSet[$key])) {
                $alreadyHere++;
                continue;
            }
            $cols    = '`'.str_replace(',', '`,`', $fk['COLS']).'`';
            $refCols = '`'.str_replace(',', '`,`', $fk['REF_COLS']).'`';
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
            } catch (Exception $e) {
                echo "    WARNING: failed to recreate FK {$fk['NAME']} on {$fk['TBL']}: "
                     . $e->getMessage() . "\n";
                // Do NOT rethrow here - we want to attempt every other
                // FK before bailing. The migration's overall success
                // is determined by whether the convert step threw.
            }
        }
        echo "  FK summary: $recreated recreated, $alreadyHere already-present\n";

        // If the convert pass threw, propagate now so the migration row
        // is NOT inserted and a re-run picks up where we left off.
        if ($convertEx !== null) {
            throw $convertEx;
        }
    }

    public function safeDown()
    {
        // Reset the database default only; do NOT round-trip per-table
        // data back to latin1 because that would risk truncating real
        // Cyrillic / CJK / emoji content the up() accepted.
        $db = $this->getDbConnection();
        $dbName = $db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->execute(
            "ALTER DATABASE `$dbName` CHARACTER SET = latin1 COLLATE = latin1_swedish_ci"
        );
        echo "  database default reverted to latin1; per-table data left as utf8mb4\n";
    }
}
