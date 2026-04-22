<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;

/**
 * This is the model class for table "tbl_bug_attachment".
 *
 * @property int $id
 * @property int $bug_change_id
 * @property string $filename
 * @property int $filesize
 * @property string $md5
 *
 * @property UploadedFile|null $fileAttachment Transient: the uploaded file
 */
class BugAttachment extends \yii\db\ActiveRecord
{
    /** @var UploadedFile|null */
    public $fileAttachment;

    public static function tableName()
    {
        return 'tbl_bug_attachment';
    }

    public function rules()
    {
        return [
            [['bug_change_id'], 'required'],
            [['bug_change_id', 'filesize'], 'integer'],
            [['filename'], 'string', 'max' => 512],
            [['md5'],      'string', 'max' => 32],
            [['fileAttachment'], 'file', 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'bug_change_id' => 'Bug Change ID',
            'filename'      => 'File Name',
            'filesize'      => 'File Size',
            'md5'           => 'MD5',
        ];
    }

    public function getBugChange()
    {
        return $this->hasOne(BugChange::class, ['id' => 'bug_change_id']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert && $this->fileAttachment instanceof UploadedFile) {
            $this->filename = $this->filename ?: ($this->fileAttachment->baseName . '.' . $this->fileAttachment->extension);
            $this->filesize = (int) $this->fileAttachment->size;
            if (empty($this->md5) && is_readable($this->fileAttachment->tempName)) {
                $this->md5 = md5_file($this->fileAttachment->tempName) ?: str_repeat('0', 32);
            }
        }

        return true;
    }

    public function persistAttachment(): void
    {
        if (!$this->fileAttachment instanceof UploadedFile) {
            return;
        }
        $project_id = $this->resolveProjectId();
        if ($project_id === null) {
            throw new \RuntimeException("Cannot persist bug attachment {$this->id}: no project context.");
        }

        $storage = Yii::$app->storage;
        $dest = $storage->bugAttachmentPath($project_id, (int) $this->id, (string) $this->filename);
        $storage->writeUploadedFile($this->fileAttachment->tempName, $dest);
    }

    public function dumpFileAttachmentContent(): void
    {
        $project_id = $this->resolveProjectId();
        if ($project_id === null) {
            throw new \yii\web\NotFoundHttpException('Bug attachment is orphaned (no project).');
        }

        $storage = Yii::$app->storage;
        $path = $storage->bugAttachmentPath($project_id, (int) $this->id, (string) $this->filename);
        $storage->streamDownload($path, (string) $this->filename, true);
    }

    public function afterDelete()
    {
        parent::afterDelete();
        if (!Yii::$app->has('storage')) {
            return;
        }
        $project_id = $this->resolveProjectId();
        if ($project_id !== null) {
            $storage = Yii::$app->storage;
            @unlink($storage->bugAttachmentPath($project_id, (int) $this->id, (string) $this->filename));
        }
    }

    /**
     * Walks bug_change -> bug -> project_id.
     */
    protected function resolveProjectId(): ?int
    {
        $change = $this->bugChange;
        if ($change === null) {
            return null;
        }
        $bug = $change->bug;
        if ($bug === null) {
            return null;
        }
        return (int) $bug->project_id;
    }
}
