<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_user_project_access".
 *
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int $usergroup_id
 */
class UserProjectAccess extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_user_project_access';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'project_id', 'usergroup_id'], 'required'],
            [['user_id', 'project_id', 'usergroup_id'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'project_id' => 'Project ID',
            'usergroup_id' => 'Usergroup ID',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUsergroup()
    {
        return $this->hasOne(Usergroup::class, ['id' => 'usergroup_id']);
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

}
