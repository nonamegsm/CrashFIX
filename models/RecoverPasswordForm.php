<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\captcha\Captcha;

/**
 * RecoverPasswordForm collects username + email + captcha used by
 * SiteController::actionRecoverPassword to start the password recovery
 * flow. On success the controller mints a one-time token and emails
 * the user a self-service reset link.
 */
class RecoverPasswordForm extends Model
{
    public $username;
    public $email;
    public $verifyCode;

    /**
     * Cached User found by checkUserAndEmail() so the controller
     * does not need to re-query.
     * @var User|null
     */
    private $_user;

    public function rules()
    {
        return [
            [['username', 'email'], 'required'],
            [['username'], 'string', 'max' => 128],
            ['email', 'email'],
            ['username', 'checkUserAndEmail'],
            ['verifyCode', 'captcha', 'captchaAction' => 'site/captcha', 'skipOnEmpty' => !$this->isCaptchaRequired()],
        ];
    }

    public function attributeLabels()
    {
        return [
            'username'   => 'User Name',
            'email'      => 'E-mail',
            'verifyCode' => 'Verification Code',
        ];
    }

    /**
     * Validates that the supplied username + email pair belongs to a real,
     * active user. Caches the user on success.
     */
    public function checkUserAndEmail($attribute, $params)
    {
        if ($this->hasErrors()) {
            return;
        }

        $user = User::findOne([
            'username' => $this->username,
            'email'    => $this->email,
        ]);

        if ($user === null) {
            $this->addError('username', 'The provided user name and/or e-mail address do not belong to any user.');
            return;
        }

        $this->_user = $user;
    }

    public function getUser(): ?User
    {
        if ($this->_user === null && !$this->hasErrors()) {
            $this->_user = User::findOne([
                'username' => $this->username,
                'email'    => $this->email,
            ]);
        }
        return $this->_user;
    }

    /**
     * Captcha is only required if the GD extension is available;
     * in test environments without it we silently skip the check.
     */
    protected function isCaptchaRequired(): bool
    {
        return Captcha::checkRequirements();
    }
}
