<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_crashgroup".
 *
 * @property int $id
 * @property int $created
 * @property int $status
 * @property int $project_id
 * @property int $appversion_id
 * @property string $title
 * @property string $md5
 */
class Crashgroup extends \yii\db\ActiveRecord
{
    const STATUS_NEW = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_crashgroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created', 'status', 'project_id', 'appversion_id', 'title', 'md5'], 'required'],
            [['created', 'status', 'project_id', 'appversion_id'], 'integer'],
            [['title'], 'string', 'max' => 256],
            [['md5'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created' => 'Created',
            'status' => 'Status',
            'project_id' => 'Project ID',
            'appversion_id' => 'Appversion ID',
            'title' => 'Title',
            'md5' => 'Md5',
        ];
    }

}
