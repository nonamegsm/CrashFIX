<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_bug_comment".
 *
 * @property int $id
 * @property string|null $text
 */
class BugComment extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug_comment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['text'], 'default', 'value' => null],
            [['text'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'text' => 'Text',
        ];
    }

}
