<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_usergroup".
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $status
 * @property int $flags
 * @property int $gperm_access_admin_panel
 * @property int $pperm_browse_crash_reports
 * @property int $pperm_browse_bugs
 * @property int $pperm_browse_debug_info
 * @property int $pperm_manage_crash_reports
 * @property int $pperm_manage_bugs
 * @property int $pperm_manage_debug_info
 * @property string $default_sidebar_tab
 * @property string $default_bug_status_filter
 */
class Usergroup extends \yii\db\ActiveRecord
{
    // Group statuses.
    const STATUS_ACTIVE  = 1;  // This group is active.
    const STATUS_DISABLED = 2; // This group is disabled (retired).


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_usergroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'description', 'status', 'flags', 'gperm_access_admin_panel', 'pperm_browse_crash_reports', 'pperm_browse_bugs', 'pperm_browse_debug_info', 'pperm_manage_crash_reports', 'pperm_manage_bugs', 'pperm_manage_debug_info', 'default_sidebar_tab', 'default_bug_status_filter'], 'required'],
            [['status', 'flags', 'gperm_access_admin_panel', 'pperm_browse_crash_reports', 'pperm_browse_bugs', 'pperm_browse_debug_info', 'pperm_manage_crash_reports', 'pperm_manage_bugs', 'pperm_manage_debug_info'], 'integer'],
            [['name'], 'string', 'max' => 32],
            [['description'], 'string', 'max' => 256],
            [['default_sidebar_tab', 'default_bug_status_filter'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'description' => 'Description',
            'status' => 'Status',
            'flags' => 'Flags',
            'gperm_access_admin_panel' => 'Gperm Access Admin Panel',
            'pperm_browse_crash_reports' => 'Pperm Browse Crash Reports',
            'pperm_browse_bugs' => 'Pperm Browse Bugs',
            'pperm_browse_debug_info' => 'Pperm Browse Debug Info',
            'pperm_manage_crash_reports' => 'Pperm Manage Crash Reports',
            'pperm_manage_bugs' => 'Pperm Manage Bugs',
            'pperm_manage_debug_info' => 'Pperm Manage Debug Info',
            'default_sidebar_tab' => 'Default Sidebar Tab',
            'default_bug_status_filter' => 'Default Bug Status Filter',
        ];
    }

}
