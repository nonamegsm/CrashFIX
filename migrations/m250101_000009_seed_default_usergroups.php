<?php

use yii\db\Migration;

/**
 * Seeds the default user groups: Admin, Dev, QA, Guest.
 */
class m250101_000009_seed_default_usergroups extends Migration
{
    public function safeUp()
    {
        $rows = [
            // [name, description, status, flags,
            //  gperm_access_admin_panel,
            //  pperm_browse_crash_reports, pperm_browse_bugs, pperm_browse_debug_info,
            //  pperm_manage_crash_reports, pperm_manage_bugs, pperm_manage_debug_info,
            //  default_sidebar_tab, default_bug_status_filter]
            ['Admin', 'Administrators',    1, 3, 1, 1, 1, 1, 1, 1, 1, 'Digest', 'open'],
            ['Dev',   'Developers',        1, 1, 0, 1, 1, 1, 1, 1, 1, 'Digest', 'owned'],
            ['QA',    'Quality Assurance', 1, 1, 0, 1, 1, 0, 1, 1, 0, 'Digest', 'verify'],
            ['Guest', 'Limited Users',     1, 1, 0, 1, 1, 0, 0, 0, 0, 'Digest', 'open'],
        ];

        foreach ($rows as $g) {
            $this->insert('{{%usergroup}}', [
                'name'                       => $g[0],
                'description'                => $g[1],
                'status'                     => $g[2],
                'flags'                      => $g[3],
                'gperm_access_admin_panel'   => $g[4],
                'pperm_browse_crash_reports' => $g[5],
                'pperm_browse_bugs'          => $g[6],
                'pperm_browse_debug_info'    => $g[7],
                'pperm_manage_crash_reports' => $g[8],
                'pperm_manage_bugs'          => $g[9],
                'pperm_manage_debug_info'    => $g[10],
                'default_sidebar_tab'        => $g[11],
                'default_bug_status_filter'  => $g[12],
            ]);
        }
    }

    public function safeDown()
    {
        $this->delete('{{%usergroup}}', ['name' => ['Admin', 'Dev', 'QA', 'Guest']]);
    }
}
