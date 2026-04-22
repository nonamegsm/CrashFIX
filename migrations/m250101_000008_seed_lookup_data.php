<?php

use yii\db\Migration;

/**
 * Seeds the lookup table with status/priority/etc. enum values used
 * across the CrashFix application.
 */
class m250101_000008_seed_lookup_data extends Migration
{
    public function safeUp()
    {
        $rows = [
            // [name, type, code, position]
            ['Waiting',    'CrashReportStatus', 1, 1],
            ['Processing', 'CrashReportStatus', 2, 2],
            ['Ready',      'CrashReportStatus', 3, 3],
            ['Invalid',    'CrashReportStatus', 4, 4],

            ['Waiting',    'DebugInfoStatus', 1, 1],
            ['Processing', 'DebugInfoStatus', 2, 2],
            ['Ready',      'DebugInfoStatus', 3, 3],
            ['Invalid',    'DebugInfoStatus', 4, 4],

            ['Active',   'UserStatus', 1, 1],
            ['Disabled', 'UserStatus', 2, 2],

            ['Active',   'UserGroupStatus', 1, 1],
            ['Disabled', 'UserGroupStatus', 2, 2],

            ['Active',   'ProjectStatus', 1, 1],
            ['Disabled', 'ProjectStatus', 2, 2],

            ['Started',   'OperationStatus', 1, 1],
            ['Succeeded', 'OperationStatus', 2, 2],
            ['Failed',    'OperationStatus', 3, 3],

            ['No symbols loaded.', 'SymLoadStatus', 0, 0],
            ['Symbols loaded.',    'SymLoadStatus', 1, 1],

            ['New',       'BugStatus', 1,   1],
            ['Reviewed',  'BugStatus', 2,   2],
            ['Accepted',  'BugStatus', 3,   3],
            ['Started',   'BugStatus', 4,   4],
            ['Fixed',     'BugStatus', 101, 5],
            ['Verified',  'BugStatus', 102, 6],
            ['Duplicate', 'BugStatus', 103, 7],
            ['WontFix',   'BugStatus', 104, 8],

            ['Low',      'BugPriority', 1, 2],
            ['Medium',   'BugPriority', 2, 1],
            ['High',     'BugPriority', 3, 3],
            ['Critical', 'BugPriority', 4, 4],

            ['NotTried',  'BugReproducability', 1, 1],
            ['Never',     'BugReproducability', 2, 2],
            ['Sometimes', 'BugReproducability', 3, 3],
            ['Always',    'BugReproducability', 4, 4],
        ];

        foreach ($rows as $r) {
            $this->insert('{{%lookup}}', [
                'name'     => $r[0],
                'type'     => $r[1],
                'code'     => $r[2],
                'position' => $r[3],
            ]);
        }
    }

    public function safeDown()
    {
        $this->delete('{{%lookup}}');
    }
}
