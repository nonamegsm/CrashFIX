<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_appversion".
 *
 * @property int $id
 * @property int $project_id
 * @property string $version
 */
class Appversion extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_appversion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'version'], 'required'],
            [['project_id'], 'integer'],
            [['version'], 'string', 'max' => 32],
            [['project_id', 'version'], 'unique', 'targetAttribute' => ['project_id', 'version']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project ID',
            'version' => 'Version',
        ];
    }

    public static function createIfNotExists($version, $projectId)
    {
        $model = self::findOne(['version' => $version, 'project_id' => $projectId]);
        if ($model === null) {
            $model = new self();
            $model->version = $version;
            $model->project_id = $projectId;
            $model->save();
        }
        return $model;
    }
}
