<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_authitemchild".
 *
 * @property string $parent
 * @property string $child
 *
 * @property Authitem $child0
 * @property Authitem $parent0
 */
class Authitemchild extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_authitemchild';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['parent', 'child'], 'required'],
            [['parent', 'child'], 'string', 'max' => 64],
            [['parent', 'child'], 'unique', 'targetAttribute' => ['parent', 'child']],
            [['parent'], 'exist', 'skipOnError' => true, 'targetClass' => Authitem::class, 'targetAttribute' => ['parent' => 'name']],
            [['child'], 'exist', 'skipOnError' => true, 'targetClass' => Authitem::class, 'targetAttribute' => ['child' => 'name']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'parent' => 'Parent',
            'child' => 'Child',
        ];
    }

    /**
     * Gets query for [[Child0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChild0()
    {
        return $this->hasOne(Authitem::class, ['name' => 'child']);
    }

    /**
     * Gets query for [[Parent0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent0()
    {
        return $this->hasOne(Authitem::class, ['name' => 'parent']);
    }

}
