<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_fileitem".
 *
 * @property int $id
 * @property int $crashreport_id
 * @property string $filename
 * @property string|null $description
 */
class Fileitem extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_fileitem';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description'], 'default', 'value' => null],
            [['crashreport_id', 'filename'], 'required'],
            [['crashreport_id'], 'integer'],
            [['filename', 'description'], 'string', 'max' => 512],
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
            'filename' => 'Filename',
            'description' => 'Description',
        ];
    }

}
