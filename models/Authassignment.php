<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_authassignment".
 *
 * @property string $itemname
 * @property string $userid
 * @property string|null $bizrule
 * @property string|null $data
 *
 * @property Authitem $itemname0
 */
class Authassignment extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_authassignment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['bizrule', 'data'], 'default', 'value' => null],
            [['itemname', 'userid'], 'required'],
            [['bizrule', 'data'], 'string'],
            [['itemname', 'userid'], 'string', 'max' => 64],
            [['itemname', 'userid'], 'unique', 'targetAttribute' => ['itemname', 'userid']],
            [['itemname'], 'exist', 'skipOnError' => true, 'targetClass' => Authitem::class, 'targetAttribute' => ['itemname' => 'name']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'itemname' => 'Itemname',
            'userid' => 'Userid',
            'bizrule' => 'Bizrule',
            'data' => 'Data',
        ];
    }

    /**
     * Gets query for [[Itemname0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItemname0()
    {
        return $this->hasOne(Authitem::class, ['name' => 'itemname']);
    }

}
