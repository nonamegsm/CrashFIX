<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_authitem".
 *
 * @property string $name
 * @property int $type
 * @property string|null $description
 * @property string|null $bizrule
 * @property string|null $data
 *
 * @property Authassignment[] $authassignments
 * @property Authitemchild[] $authitemchildren
 * @property Authitemchild[] $authitemchildren0
 * @property Authitem[] $children
 * @property Authitem[] $parents
 */
class Authitem extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_authitem';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['description', 'bizrule', 'data'], 'default', 'value' => null],
            [['name', 'type'], 'required'],
            [['type'], 'integer'],
            [['description', 'bizrule', 'data'], 'string'],
            [['name'], 'string', 'max' => 64],
            [['name'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'name' => 'Name',
            'type' => 'Type',
            'description' => 'Description',
            'bizrule' => 'Bizrule',
            'data' => 'Data',
        ];
    }

    /**
     * Gets query for [[Authassignments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAuthassignments()
    {
        return $this->hasMany(Authassignment::class, ['itemname' => 'name']);
    }

    /**
     * Gets query for [[Authitemchildren]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAuthitemchildren()
    {
        return $this->hasMany(Authitemchild::class, ['parent' => 'name']);
    }

    /**
     * Gets query for [[Authitemchildren0]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAuthitemchildren0()
    {
        return $this->hasMany(Authitemchild::class, ['child' => 'name']);
    }

    /**
     * Gets query for [[Children]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(Authitem::class, ['name' => 'child'])->viaTable('tbl_authitemchild', ['parent' => 'name']);
    }

    /**
     * Gets query for [[Parents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParents()
    {
        return $this->hasMany(Authitem::class, ['name' => 'parent'])->viaTable('tbl_authitemchild', ['child' => 'name']);
    }

}
