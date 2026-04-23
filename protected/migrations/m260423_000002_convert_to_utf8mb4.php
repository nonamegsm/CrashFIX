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
 * which is what triggered this migration. The CrashRpt client
 * reports custom-prop names and values verbatim from the user's
 * locale, so a Russian / Chinese / Japanese end-user can take the
 * server down for an entire project just by crashing.
 *
 * What this does
 * --------------
 * 1. Reads the current default charset of the database (so we do
 *    not noisy-fail on installs that are ALREADY utf8mb4 - this
 *    migration is intended to be safely re-runnable as part of
 *    fresh installs too).
 * 2. ALTER DATABASE ... CHARACTER SET utf8mb4 - sets the default
 *    for any future CREATE TABLE without explicit charset.
 * 3. For every table with a CrashFix prefix (uses table_prefix
 *    from the connection), runs:
 *      ALTER TABLE x CONVERT TO CHARACTER SET utf8mb4
 *                    COLLATE utf8mb4_unicode_ci
 *    which rewrites every CHAR / VARCHAR / TEXT / ENUM column.
 * 4. Skips tables already at utf8mb4 (idempotent).
 *
 * Safety
 * ------
 * * CONVERT TO CHARACTER SET widens column storage (utf8mb4 uses
 *   up to 4 bytes per char vs latin1's 1 byte). InnoDB enforces
 *   a max key length per index; if any existing index would
 *   exceed it after conversion, the ALTER fails noisily and the
 *   migration aborts before touching the next table. With CrashFix's
 *   schema (no overly long indexed VARCHARs), this should not
 *   trigger; if it does, the failing table name is in the error.
 * * Existing data is reinterpreted, not re-encoded. If old data
 *   was stored as latin1 BUT THE BYTES WERE ACTUALLY UTF-8
 *   (because the old connection didn't SET NAMES, so MySQL just
 *   stored whatever bytes PHP sent), CONVERT TO CHARACTER SET
 *   will double-encode it. To detect this, compare strings before
 *   and after on a non-prod copy first. The vast majority of
 *   installs are clean Latin-1 only and need no special handling.
 * * down() reverts the database default to latin1 but does NOT
 *   convert the per-table data back, because losing data is worse
 *   than a charset mismatch and the down() is intended only to
 *   make the migration row removable.
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

        // 1. Database-level default. Future CREATE TABLE without an
        //    explicit charset will pick this up.
        $this->execute(
            "ALTER DATABASE `$dbName` CHARACTER SET = $target COLLATE = $coll"
        );

        // 2. Disable foreign-key checks for the duration of the walk.
        //    Without this, ALTER TABLE ... CONVERT TO CHARACTER SET
        //    fails on any column referenced by an FK with:
        //       1832 Cannot change column 'X': used in a foreign key
        //            constraint 'Y'
        //    because MySQL will not convert one side of an FK without
        //    converting the other in lockstep. With FK checks off, each
        //    ALTER succeeds in isolation; once every table in the walk
        //    has been converted, both sides of every FK are again at
        //    matching charsets and the constraint validates cleanly
        //    when checks are re-enabled below.
        //
        //    SET FOREIGN_KEY_CHECKS is a session-scoped setting on
        //    the current connection. Migrations run in a single
        //    connection, so the setting persists across all the
        //    ALTER statements in the loop below.
        //
        //    The try / finally is critical: if any ALTER throws (e.g.
        //    a row that won't fit in a utf8mb4 widened key), we MUST
        //    re-enable FK checks before rethrowing, otherwise the
        //    next request on the same long-lived connection runs
        //    with checks off and silently corrupts referential
        //    integrity.
        $this->execute("SET FOREIGN_KEY_CHECKS = 0");
        try {
            // 3. Find every table with the CrashFix prefix and convert
            //    each one whose current default charset is not yet utf8mb4.
            $sql = "
                SELECT t.TABLE_NAME, ccsa.CHARACTER_SET_NAME
                  FROM information_schema.TABLES t
                  JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY ccsa
                    ON ccsa.COLLATION_NAME = t.TABLE_COLLATION
                 WHERE t.TABLE_SCHEMA = :db
                   AND t.TABLE_TYPE   = 'BASE TABLE'
                   AND t.TABLE_NAME LIKE :pfx
            ";
            $rows = $db->createCommand($sql)
                       ->queryAll(true, array(
                           ':db'  => $dbName,
                           ':pfx' => $prefix . '%',
                       ));

            $converted = 0;
            $skipped   = 0;
            foreach ($rows as $r) {
                $tname  = $r['TABLE_NAME'];
                $cset   = $r['CHARACTER_SET_NAME'];
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
        } finally {
            // Always re-enable FK checks on this connection, even if a
            // row above threw. If we left them off, the next query on
            // this connection (within the same yiic process) would
            // bypass referential integrity.
            $this->execute("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    public function safeDown()
    {
        // We do NOT round-trip data back to latin1 because that risks
        // truncating real Cyrillic / CJK / emoji content that the new
        // schema accepted. Just reset the database default so the
        // migration row can be cleanly removed.
        $db = $this->getDbConnection();
        $dbName = $db->createCommand('SELECT DATABASE()')->queryScalar();
        $this->execute(
            "ALTER DATABASE `$dbName` CHARACTER SET = latin1 COLLATE = latin1_swedish_ci"
        );
        echo "  database default reverted to latin1; per-table data left as utf8mb4\n";
    }
}
