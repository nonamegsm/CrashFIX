<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_customprop".
 *
 * @property int $id
 * @property int $crashreport_id
 * @property string $name
 * @property string $value
 */
class Customprop extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_customprop';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['crashreport_id', 'name', 'value'], 'required'],
            [['crashreport_id'], 'integer'],
            [['value'], 'string'],
            [['name'], 'string', 'max' => 128],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'crashreport_id' => 'Crashreport ID',
            'name' => 'Name',
            'value' => 'Value',
        ];
    }

}
