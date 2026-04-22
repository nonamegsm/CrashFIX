<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;

/**
 * This is the model class for table "tbl_debuginfo".
 *
 * @property int $id
 * @property int $project_id
 * @property int $dateuploaded
 * @property int $status
 * @property string $filename
 * @property string $guid
 * @property string $md5
 * @property int $filesize
 *
 * @property UploadedFile|null $fileAttachment Transient: the uploaded file
 */
class Debuginfo extends \yii\db\ActiveRecord
{
    // Status codes match the seeded `lookup` table (DebugInfoStatus).
    const STATUS_WAITING    = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_READY      = 3;
    const STATUS_INVALID    = 4;

    /** @var UploadedFile|null Transient upload to be persisted in afterSave. */
    public $fileAttachment;

    public static function tableName()
    {
        return 'tbl_debuginfo';
    }

    public function rules()
    {
        return [
            [['project_id'], 'required'],
            [['project_id', 'dateuploaded', 'status', 'filesize'], 'integer'],
            [['filename'], 'string', 'max' => 512],
            [['guid'],     'string', 'max' => 48],
            [['md5'],      'string', 'max' => 32],
            [['fileAttachment'], 'file', 'skipOnEmpty' => true, 'extensions' => ['pdb', 'sym', 'dbg']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'project_id'   => 'Project',
            'dateuploaded' => 'Date Uploaded',
            'status'       => 'Status',
            'filename'     => 'File Name',
            'guid'         => 'Module GUID',
            'md5'          => 'MD5',
            'filesize'     => 'File Size',
        ];
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->dateuploaded = $this->dateuploaded ?: time();
            $this->status       = $this->status ?: self::STATUS_WAITING;

            if ($this->fileAttachment instanceof UploadedFile) {
                $this->filename = $this->filename ?: ($this->fileAttachment->baseName . '.' . $this->fileAttachment->extension);
                $this->filesize = (int) $this->fileAttachment->size;
                if (empty($this->md5) && is_readable($this->fileAttachment->tempName)) {
                    $this->md5 = md5_file($this->fileAttachment->tempName) ?: str_repeat('0', 32);
                }
            }

            if (empty($this->guid)) {
                // Best-effort fallback when daemon hasn't told us the
                // PE/PDB GUID yet. Treated as opaque so the unique index
                // on (project_id,guid) still functions.
                $this->guid = strtoupper(bin2hex(random_bytes(16)));
            }
            if (empty($this->md5)) {
                $this->md5 = str_repeat('0', 32);
            }
            if (empty($this->filename)) {
                $this->filename = 'unknown.pdb';
            }
            if (empty($this->filesize)) {
                $this->filesize = 0;
            }
        }

        return true;
    }

    public function persistAttachment(): void
    {
        if (!$this->fileAttachment instanceof UploadedFile) {
            return;
        }
        $storage = Yii::$app->storage;
        $dest = $storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename);
        $storage->writeUploadedFile($this->fileAttachment->tempName, $dest);
    }

    public function afterDelete()
    {
        parent::afterDelete();
        if (!Yii::$app->has('storage')) {
            return;
        }
        $storage = Yii::$app->storage;
        @unlink($storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename));
    }

    public function dumpFileAttachmentContent(): void
    {
        $storage = Yii::$app->storage;
        $path = $storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename);
        $storage->streamDownload($path, (string) $this->filename, true);
    }

    /**
     * Soft-delete: mark the row as Invalid so the daemon will pick it up
     * for cleanup. Falls back to hard delete if the daemon never claims
     * it.
     */
    public function markForDeletion(): bool
    {
        $this->status = self::STATUS_INVALID;
        return $this->save(false, ['status']);
    }

    /**
     * True when a record with the same (project_id, guid) already exists.
     * Used by the external upload endpoint to avoid duplicate uploads.
     */
    public function checkFileGUIDExists(): bool
    {
        if (empty($this->guid) || empty($this->project_id)) {
            return false;
        }
        return self::find()
            ->where(['project_id' => $this->project_id, 'guid' => $this->guid])
            ->andWhere(['<>', 'status', self::STATUS_INVALID])
            ->exists();
    }
}
