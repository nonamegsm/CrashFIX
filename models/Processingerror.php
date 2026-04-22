<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_processingerror".
 *
 * @property int $id
 * @property int $type
 * @property int $srcid
 * @property string|null $message
 */
class Processingerror extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_processingerror';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['message'], 'default', 'value' => null],
            [['type', 'srcid'], 'required'],
            [['type', 'srcid'], 'integer'],
            [['message'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'srcid' => 'Srcid',
            'message' => 'Message',
        ];
    }

}
