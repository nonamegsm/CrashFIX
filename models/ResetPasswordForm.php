<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * ResetPasswordForm is the model behind the reset password form.
 */
class ResetPasswordForm extends Model
{
    public $password;
    public $password_repeat;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['password', 'password_repeat'], 'required'],
            ['password', 'string', 'min' => 4],
            ['password_repeat', 'compare', 'compareAttribute' => 'password', 'message' => "Passwords don't match."],
        ];
    }

    /**
     * @return array customized attribute labels
     */
    public function attributeLabels()
    {
        return [
            'password' => 'New Password',
            'password_repeat' => 'Repeat Password',
        ];
    }
}
