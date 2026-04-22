<?php

namespace app\models;

use Yii;
use yii\base\Model;

class LoginForm extends Model
{
    public $username;
    public $password;
    public $rememberMe = true;
    public $oneTimeAccessToken;

    private $_user = false;

    public function rules()
    {
        return [
            [['username', 'password'], 'required', 'on' => 'RegularLogin'],
            ['rememberMe', 'boolean', 'on' => 'RegularLogin'],
            ['password', 'validatePassword', 'on' => 'RegularLogin'],
            ['oneTimeAccessToken', 'validateAccessToken', 'on' => 'OneTimeLogin'],
        ];
    }

    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, 'Incorrect username or password.');
            }
        }
    }

    public function validateAccessToken($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = User::findIdentityByAccessToken($this->oneTimeAccessToken);
            if (!$user) {
                $this->addError('username', 'Incorrect access token.');
            } else {
                $this->_user = $user;
            }
        }
    }

    public function login()
    {
        if ($this->validate()) {
            return Yii::$app->user->login($this->getUser(), $this->rememberMe ? 3600*24*30 : 0);
        }
        return false;
    }

    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByUsername($this->username);
        }

        return $this->_user;
    }
}
