<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_bug_change".
 *
 * @property int $id
 * @property int $bug_id
 * @property int $timestamp
 * @property int $user_id
 * @property int $flags
 * @property int|null $status_change_id
 * @property int|null $comment_id
 */
class BugChange extends \yii\db\ActiveRecord
{
    // bug_change.flags bitfield. Mirrors legacy CrashFix constants.
    const FLAG_INITIAL_CHANGE = 0x1; // This row corresponds to the bug being opened.

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug_change';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status_change_id', 'comment_id'], 'default', 'value' => null],
            [['bug_id', 'timestamp', 'user_id', 'flags'], 'required'],
            [['bug_id', 'timestamp', 'user_id', 'flags', 'status_change_id', 'comment_id'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'bug_id' => 'Bug ID',
            'timestamp' => 'Timestamp',
            'user_id' => 'User ID',
            'flags' => 'Flags',
            'status_change_id' => 'Status Change ID',
            'comment_id' => 'Comment ID',
        ];
    }

    public function getBug()
    {
        return $this->hasOne(Bug::class, ['id' => 'bug_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getStatuschange()
    {
        return $this->hasOne(BugStatusChange::class, ['id' => 'status_change_id']);
    }

    public function getComment()
    {
        return $this->hasOne(BugComment::class, ['id' => 'comment_id']);
    }

    public function getAttachments()
    {
        return $this->hasMany(BugAttachment::class, ['bug_change_id' => 'id']);
    }

    public function isInitialChange(): bool
    {
        return ((int) $this->flags & self::FLAG_INITIAL_CHANGE) !== 0;
    }
}
