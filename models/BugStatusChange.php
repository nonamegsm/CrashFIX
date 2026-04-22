<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_bug_status_change".
 *
 * @property int $id
 * @property int|null $status
 * @property int|null $assigned_to
 * @property int|null $priority
 * @property int|null $reproducability
 * @property int|null $merged_into
 */
class BugStatusChange extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug_status_change';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['status', 'assigned_to', 'priority', 'reproducability', 'merged_into'], 'default', 'value' => null],
            [['status', 'assigned_to', 'priority', 'reproducability', 'merged_into'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'status' => 'Status',
            'assigned_to' => 'Assigned To',
            'priority' => 'Priority',
            'reproducability' => 'Reproducability',
            'merged_into' => 'Merged Into',
        ];
    }

    public function getOwner()
    {
        if ($this->assigned_to === null || (int) $this->assigned_to <= 0) {
            return null;
        }
        return User::findOne((int) $this->assigned_to);
    }
}
