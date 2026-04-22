<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_project".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $status
 * @property int $crash_reports_per_group_quota
 * @property int $crash_report_files_disc_quota
 * @property int $bug_attachment_files_disc_quota
 * @property int $debug_info_files_disc_quota
 * @property int $require_exact_build_age
 */
class Project extends \yii\db\ActiveRecord
{
    // Project statuses (aligned with the seeded `lookup` table values).
    const STATUS_ACTIVE   = 1; // This is an active project.
    const STATUS_DISABLED = 2; // This is a disabled project.
   /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_project';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description'], 'default', 'value' => null],
            [['status', 'crash_reports_per_group_quota', 'crash_report_files_disc_quota', 'bug_attachment_files_disc_quota', 'debug_info_files_disc_quota', 'require_exact_build_age'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['crash_reports_per_group_quota'], 'default', 'value' => 1000],
            [['crash_report_files_disc_quota'], 'default', 'value' => 5242880000], // 5GB
            [['bug_attachment_files_disc_quota'], 'default', 'value' => 1048576000], // 1GB
            [['debug_info_files_disc_quota'], 'default', 'value' => 5242880000], // 5GB
            [['require_exact_build_age'], 'default', 'value' => 0],
            [['name', 'status', 'crash_reports_per_group_quota', 'crash_report_files_disc_quota', 'bug_attachment_files_disc_quota', 'debug_info_files_disc_quota', 'require_exact_build_age'], 'required'],
            [['status', 'crash_reports_per_group_quota', 'crash_report_files_disc_quota', 'bug_attachment_files_disc_quota', 'debug_info_files_disc_quota', 'require_exact_build_age'], 'integer'],
            [['name'], 'string', 'max' => 32],
            [['description'], 'string', 'max' => 256],
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
            'crash_reports_per_group_quota' => 'Crash Reports Per Group Quota',
            'crash_report_files_disc_quota' => 'Crash Report Files Disc Quota',
            'bug_attachment_files_disc_quota' => 'Bug Attachment Files Disc Quota',
            'debug_info_files_disc_quota' => 'Debug Info Files Disc Quota',
            'require_exact_build_age' => 'Require exact build age for debugging symbols',
        ];
    }

    /**
     * Returns count of crash reports in this project.
     */
    public function getCrashReportCount(&$totalFileSize, &$percentOfDiskQuota, $appver = -1 /* PROJ_VER_ALL */)
    {
        $query = CrashReport::find()->where(['project_id' => $this->id]);
        if ($appver != -1) {
            $query->andWhere(['appversion_id' => $appver]);
        }
        
        $count = $query->count();
        $totalFileSize = $query->sum('filesize') ?: 0;
                
        if ($this->crash_report_files_disc_quota <= 0) {
            $percentOfDiskQuota = -1; 
        } else {
            $percentOfDiskQuota = 100 * $totalFileSize / ($this->crash_report_files_disc_quota * 1024 * 1024);
        }
        return $count;
    }

    /**
     * Returns count of bug attachment files in this project.
     */
    public function getBugAttachmentCount(&$totalFileSize, &$percentOfDiskQuota, $appver = -1 /* PROJ_VER_ALL */)
    {
        // Bug attachments are linked via bug_change and bug
        // For simplicity in Yii 2 migration we just do a direct query on BugAttachment
        // Joined with bug via bug_change
        $query = \app\models\BugAttachment::find()
            ->innerJoin('tbl_bug_change bc', 'tbl_bug_attachment.bug_change_id = bc.id')
            ->innerJoin('tbl_bug b', 'bc.bug_id = b.id')
            ->where(['b.project_id' => $this->id]);
            
        if ($appver != -1) {
            $query->andWhere(['b.appversion_id' => $appver]);
        }
        
        $count = $query->count();
        $totalFileSize = $query->sum('filesize') ?: 0;
                
        if ($this->bug_attachment_files_disc_quota <= 0) {
            $percentOfDiskQuota = -1;
        } else {
            $percentOfDiskQuota = 100 * $totalFileSize / ($this->bug_attachment_files_disc_quota * 1024 * 1024);
        }
        return $count;
    }

    /**
     * Returns count of debug info files in this project.
     */
    public function getDebugInfoCount(&$totalFileSize, &$percentOfDiskQuota)
    {
        $query = \app\models\DebugInfo::find()->where(['project_id' => $this->id]);
        
        $count = $query->count();
        $totalFileSize = $query->sum('filesize') ?: 0;
                
        if ($this->debug_info_files_disc_quota <= 0) {
            $percentOfDiskQuota = -1;
        } else {
            $percentOfDiskQuota = 100 * $totalFileSize / ($this->debug_info_files_disc_quota * 1024 * 1024);
        }
        return $count;
    }

    /**
     * Returns the array of top crash groups.
     */
    public function getTopCrashGroups($appver = null)
    {
        $query = \app\models\CrashGroup::find()
            ->select(['tbl_crashgroup.*', 'COUNT(tbl_crashreport.id) AS crashReportCount'])
            ->innerJoin('tbl_crashreport', 'tbl_crashreport.groupid = tbl_crashgroup.id')
            ->where(['tbl_crashgroup.project_id' => $this->id])
            ->groupBy('tbl_crashgroup.id')
            ->orderBy('crashReportCount DESC')
            ->limit(10);
            
        if ($appver != null && $appver != -1) {
            $query->andWhere(['tbl_crashgroup.appversion_id' => $appver]);
        }       
        
        return $query->all();
    }

    /**
     * Returns the array of recent bugs.
     */
    public function getRecentBugChanges($appver = null)
    {
        $query = \app\models\BugChange::find()
            ->joinWith('bug')
            ->where(['tbl_bug.project_id' => $this->id])
            ->orderBy('tbl_bug_change.timestamp DESC')
            ->limit(10);
            
        if ($appver != null && $appver != '-1') {
            $query->andWhere(['tbl_bug.appversion_id' => $appver]);
        }       
        
        return $query->all();
    }

    public function getUsers()
    {
        return $this->hasMany(UserProjectAccess::class, ['project_id' => 'id']);
    }

    public function getAppversions()
    {
        return $this->hasMany(Appversion::class, ['project_id' => 'id']);
    }

    /**
     * Toggle the project's effective status. Pass `true` to activate,
     * `false` to disable. Performs a partial save of the `status` column.
     */
    public function enable(bool $enabled): bool
    {
        $this->status = $enabled ? self::STATUS_ACTIVE : self::STATUS_DISABLED;
        return $this->save(false, ['status']);
    }
}

