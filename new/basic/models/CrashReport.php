<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\base\Exception;

/**
 * This is the model class for table "{{%crashreport}}".
 */
class CrashReport extends ActiveRecord
{
    // Crash report status codes
    const STATUS_PENDING_PROCESSING     = 1; // The crash report awaiting processing by daemon
    const STATUS_PROCESSING_IN_PROGRESS = 2; // The debug info file is currently being processed by daemon.
    const STATUS_PROCESSED              = 3; // The crash report was processed by daemon
    const STATUS_INVALID                = 4; // The crash report file is marked by daemon as an invalid PDB file.
    
    public $appversion; // Non-db field that stores appversion string for this report.
    public $fileAttachment; // File attachment UploadedFile
    public $fileAttachmentIsUploaded = true;    // Set to TRUE if attachment was uploaded; or set to false if it resides in local filesystem.
    public $ignoreFileAttachmentErrors = false; // Used in tests
    public $cnt; // Count (used in stat query)
    
    // Search related attributes
    public $filter; // Simple search filter.
    public $isAdvancedSearch = false; // Is advanced search enabled?
    public $receivedFrom; // Received from time(used in advanced search).
    public $receivedTo;   // Received to time (used in advanced search).

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%crashreport}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['md5'], 'string', 'length' => 32],
            [['crashguid'], 'string', 'length' => 36],
            [['appversion'], 'string', 'max' => 32],
            [['description'], 'string', 'max' => 256],
            [['crashrptver'], 'integer', 'min' => 1000, 'max' => 9999],
            [['id', 'received', 'status', 'md5', 'crashguid', 'appversion', 'ipaddress', 'emailfrom', 'description'], 'safe', 'on' => 'search'],
            [['receivedTo'], 'compareFromToDates', 'on' => 'search'],
            [['emailfrom'], 'email', 'on' => 'default'],
            [['crashguid'], 'checkCrashGUIDExists', 'on' => 'insert'],
            [['fileAttachment'], 'file', 'extensions' => 'zip', 'maxFiles' => 1, 'minSize' => 1],
            [['fileAttachment'], 'checkFileAttachment', 'on' => 'insert'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'fileAttachment' => 'File Attachment',
            'id' => 'ID',
            'srcfilename' => 'Report File Name',
            'filesize' => 'Size',
            'date_created' => 'Date Created',
            'received' => 'Date Received',
            'status' => 'Status',
            'ipaddress' => 'IP Address',
            'md5' => 'MD5 Hash',
            'groupid' => 'Collection',
            'crashguid' => 'Crash GUID',
            'project_id' => 'Project',
            'appversion_id' => 'Project Version',
            'emailfrom' => 'E-mail',
            'description' => 'Description',
            'crashrptver' => 'Generator Version',
            'exception_type' => 'Exception Type',
            'exception_code' => 'SEH Exception Code',
            'exceptionaddress' => 'Exception Address',
            'exceptionmodule' => 'Exception Module',
            'exceptionmodulebase' => 'Exception Module Base',
            'exe_image' => 'Image Path',
            'os_name_reg' => 'Operating System',
            'os_ver_mdmp' => 'OS Version',
            'os_is_64bit' => 'OS Bitness',
            'geo_location' => 'Geographic Location',
            'product_type' => 'Machine Type',
            'cpu_architecture' => 'CPU Architecture',
            'cpu_count' => 'CPU Count',
            'gui_resource_count' => 'GUI Resource Count',
            'open_handle_count' => 'Open Handle Count',
            'memory_usage_kbytes' => 'Memory Usage',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['date_created', 'received'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->project_id = Yii::$app->user->identity->current_project_id;
            $project = Project::findOne($this->project_id);

            if ($project === null || $project->status != Project::STATUS_ACTIVE) {
                $this->addError('project_id', 'Invalid or inactive project.');
                return false;
            }

            $appVersion = AppVersion::createIfNotExists($this->appversion ?: '(not set)', $this->project_id);
            if ($appVersion === null) {
                $this->addError('appversion', 'Invalid application version.');
                return false;
            }
            $this->appversion_id = $appVersion->id;

            $crashGroup = $this->createCrashGroup();
            if ($crashGroup === null) {
                return false;
            }
            $this->groupid = $crashGroup->id;

            if (!$this->saveFileAttachment()) {
                return false;
            }

            $this->received = time();
            $this->ipaddress = Yii::$app->request->userIP;
            $this->status = self::STATUS_PENDING_PROCESSING;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        if (!$this->deleteAssociatedRecords()) {
            return false;
        }

        $fileName = $this->getLocalFilePath();
        $dirName = dirname($fileName);
        $outerDirName = dirname($dirName);

        @unlink($fileName);
        @rmdir($dirName);
        @rmdir($outerDirName);

        return true;
    }

    /**
     * Deletes associated records.
     */
    protected function deleteAssociatedRecords()
    {
        $transaction = Yii::$app->db->beginTransaction();

        try {
            foreach ($this->threads as $thread) {
                $thread->delete();
            }

            foreach ($this->modules as $module) {
                $module->delete();
            }

            foreach ($this->fileItems as $fileItem) {
                $fileItem->delete();
            }

            foreach ($this->customProps as $customProp) {
                $customProp->delete();
            }

            foreach ($this->processingErrors as $processingError) {
                $processingError->delete();
            }

            $transaction->commit();
        } catch (Exception $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Checks if a crash report with such a GUID exists in the database or not.
     */
    public function checkCrashGUIDExists()
    {
        if (static::find()->where(['crashguid' => $this->crashguid])->exists()) {
            $this->addError('crashguid', 'Such a crash GUID is already existing in the database.');
            return false;
        }

        return true;
    }

    /**
     * Ensures that uploaded file attachment has the same MD5 hash as the 'md5' attribute of the model.
     */
    public function checkFileAttachment()
    {
        if ($this->fileAttachment === null) {
            $this->addError('fileAttachment', 'File attachment is missing.');
            return false;
        }

        $md5 = md5_file($this->fileAttachment->tempName);

        if ($md5 !== $this->md5) {
            $this->addError('fileAttachment', 'File attachment MD5 hash mismatch.');
            return false;
        }

        return true;
    }

    /**
     * Compares Date From and Date To to ensure Date To is the greater one.
     */
    public function compareFromToDates()
    {
        if ($this->scenario !== 'search') {
            return true;
        }

        if (!isset($this->receivedFrom) || !isset($this->receivedTo)) {
            return true;
        }

        $date1 = strtotime($this->receivedFrom);
        $date2 = strtotime($this->receivedTo);

        if ($date1 === false || $date2 === false) {
            return true;
        }

        if ($date2 >= $date1) {
            return true;
        }

        $this->addError('receivedTo', 'Received To must be greater or equal to Received From.');
        return false;
    }

    /**
     * Saves the uploaded file attachment.
     */
    public function saveFileAttachment()
    {
        if ($this->fileAttachment === null) {
            $this->addError('fileAttachment', 'File attachment is missing.');
            return false;
        }

        $this->srcfilename = basename($this->fileAttachment->name);
        $this->filesize = $this->fileAttachment->size;

        if (!$this->checkQuota()) {
            return false;
        }

        $this->md5 = md5_file($this->fileAttachment->tempName);

        $subDir1 = substr($this->md5, 0, 3);
        $subDir2 = substr($this->md5, 3, 3);
        $dirName = Yii::getAlias('@app/data/crashReports/' . $subDir1 . '/' . $subDir2);

        if (!is_dir($dirName) && !mkdir($dirName, 0777, true) && !is_dir($dirName)) {
            $this->addError('fileAttachment', "Couldn't make directory.");
            return false;
        }

        $fileName = $dirName . '/' . $this->md5 . '.zip';

        if ($this->fileAttachmentIsUploaded && !$this->fileAttachment->saveAs($fileName)) {
            $this->addError('fileAttachment', "Couldn't save data to local storage.");
            return false;
        }

        if (!$this->fileAttachmentIsUploaded && !copy($this->fileAttachment->tempName, $fileName)) {
            $this->addError('fileAttachment', "Couldn't copy data to local storage.");
            return false;
        }

        if (!$this->fileAttachmentIsUploaded && !unlink($this->fileAttachment->tempName)) {
            $this->addError('fileAttachment', "Couldn't remove temp attachment file.");
            return false;
        }

        return true;
    }

    /**
     * Returns the local file path.
     */
    public function getLocalFilePath()
    {
        $subDir1 = substr($this->md5, 0, 3);
        $subDir2 = substr($this->md5, 3, 3);
        return Yii::getAlias('@app/data/crashReports/' . $subDir1 . '/' . $subDir2 . '/' . $this->md5 . '.zip');
    }

    /**
     * Creates a crash group for the given crash report.
     */
    public function createCrashGroup()
    {
        $crashGroupMD5 = '';
        $crashGroupTitle = $this->getCrashGroupTitle($crashGroupMD5);

        $criteria = [
            'project_id' => $this->project_id,
            'appversion_id' => $this->appversion_id,
            'md5' => $crashGroupMD5,
        ];

        $crashGroup = CrashGroup::findOne($criteria);

        if ($crashGroup === null) {
            $crashGroup = new CrashGroup([
                'title' => $crashGroupTitle,
                'md5' => $crashGroupMD5,
                'project_id' => $this->project_id,
                'appversion_id' => $this->appversion_id,
            ]);

            if (!$crashGroup->save()) {
                return null;
            }
        }

        return $crashGroup;
    }

    /**
     * Returns the crash group title.
     */
    public function getCrashGroupTitle(&$md5)
    {
        $title = '';
        $hash = '';

        $proj = Project::findOne($this->project_id);
        if ($proj === null) {
            $title = 'Unknown Project';
        } elseif ($this->status === self::STATUS_INVALID) {
            $title = 'Invalid Reports';
        } elseif ($this->status !== self::STATUS_PROCESSED) {
            $title = 'Unsorted Reports';
        } else {
            if (isset($this->customProps)) {
                foreach ($this->customProps as $prop) {
                    if ($prop->name === 'CollectionId') {
                        $title = $prop->value;
                    }
                }
            }

            if (strlen($title) === 0 && $this->exception_thread_id === 0) {
                $title = 'Reports without Exception Info';
            }

            if (strlen($title) === 0) {
                foreach ($this->threads as $thread) {
                    if ($thread->thread_id === $this->exception_thread_id) {
                        $title = $thread->getExceptionStackFrameTitle();
                        $hash = $thread->stack_trace_md5;
                        break;
                    }
                }
            }

            if (strlen($title) === 0) {
                if (isset($this->exceptionmodule) && isset($this->exceptionmodulebase)) {
                    $shortModuleName = basename($this->exceptionmodule);
                    $offsetInModule = $this->exceptionaddress - $this->exceptionmodulebase;
                    $title = sprintf("%s!+0x%x", $shortModuleName, $offsetInModule);
                } else {
                    $title = 'Reports without Exception Info';
                }
            }
        }

        $title = MiscHelpers::addEllipsis($title, 200);

        if (strlen($hash) === 0) {
            $hash = md5($title);
        }

        $md5 = $hash;

        return $title;
    }

    /**
     * Checks if project quota allows to add this crash report to project.
     */
    public function checkQuota()
    {
        $project = Project::findOne($this->project_id);
        if ($project === null) {
            $this->addError('project_id', 'Invalid project ID.');
            return false;
        }

        if ($project->crash_report_files_disc_quota > 0) {
            $totalFileSize = 0;
            $percentOfQuota = 0;
            $project->getCrashReportCount($totalFileSize, $percentOfQuota);

            if ($project->crash_report_files_disc_quota * 1024 * 1024 < $totalFileSize + $this->filesize) {
                $this->addError('fileAttachment', 'Crash report disc quota for this project has exceeded.');
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the list of processing errors associated with this crash report.
     */
    public function getProcessingErrors()
    {
        return $this->hasMany(ProcessingError::class, ['srcid' => 'id'])
            ->andWhere(['type' => ProcessingError::TYPE_CRASH_REPORT_ERROR])
            ->all();
    }

    /**
     * Checks if the status of crash report can be reset to Waiting.
     */
    public function canResetStatus()
    {
        return $this->status === self::STATUS_PROCESSED || $this->status === self::STATUS_INVALID;
    }

    /**
     * Resets crash report's status to Waiting.
     */
    public function resetStatus()
    {
        if (!$this->canResetStatus()) {
            throw new Exception('Unexpected error.');
        }

        $this->status = self::STATUS_PENDING_PROCESSING;

        if (!$this->save()) {
            throw new Exception('Unexpected error.');
        }
    }
}
