<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_bug_crashreport".
 *
 * @property int $id
 * @property int $bug_id
 * @property int $crashreport_id
 */
class BugCrashreport extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug_crashreport';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bug_id', 'crashreport_id'], 'required'],
            [['bug_id', 'crashreport_id'], 'integer'],
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
            'crashreport_id' => 'Crashreport ID',
        ];
    }

}
