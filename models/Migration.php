<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_migration".
 *
 * @property string $version
 * @property int|null $apply_time
 */
class Migration extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_migration';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['apply_time'], 'default', 'value' => null],
            [['version'], 'required'],
            [['apply_time'], 'integer'],
            [['version'], 'string', 'max' => 180],
            [['version'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'version' => 'Version',
            'apply_time' => 'Apply Time',
        ];
    }

}
