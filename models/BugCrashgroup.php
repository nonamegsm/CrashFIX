<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_bug_crashgroup".
 *
 * @property int $id
 * @property int $bug_id
 * @property int $crashgroup_id
 */
class BugCrashgroup extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug_crashgroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bug_id', 'crashgroup_id'], 'required'],
            [['bug_id', 'crashgroup_id'], 'integer'],
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
            'crashgroup_id' => 'Crashgroup ID',
        ];
    }

}
