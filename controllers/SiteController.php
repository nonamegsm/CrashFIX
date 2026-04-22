<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\RecoverPasswordForm;
use app\models\ResetPasswordForm;
use app\models\User;
use app\components\MiscHelpers;

class SiteController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem;
    public $adminMenuItem;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!is_file(Yii::getAlias('@app/config/installed.txt'))) {
            if ($action->id !== 'error') {
                return $this->redirect(['install/index'])->send();
            }
        }

        return true;
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'logout', 'reset-password', 'set-cur-project', 'check-daemon', 'admin', 'daemon', 'daemon-status'],
                'rules' => [
                    [
                        'actions' => ['index', 'logout', 'reset-password', 'set-cur-project', 'check-daemon'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['admin', 'daemon', 'daemon-status'],
                        'allow' => true,
                        'roles' => ['gperm_access_admin_panel'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
                'layout' => 'column1',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin($prt = null)
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($prt) {
            $model->scenario = 'OneTimeLogin';
            $model->oneTimeAccessToken = $prt;
            if ($model->validate() && $model->login()) {
                // Single-use: invalidate the token now and force a password
                // change on the very next page so the link cannot be replayed.
                $user = Yii::$app->user->identity;
                if ($user instanceof User) {
                    $user->pwd_reset_token = null;
                    $user->flags |= User::FLAG_PASSWORD_RESETTED;
                    $user->save(false, ['pwd_reset_token', 'flags']);
                }
                return $this->redirect(['site/reset-password']);
            }
            throw new \yii\web\ForbiddenHttpException('Invalid request');
        }

        $model->scenario = 'RegularLogin';
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            $user = Yii::$app->user->identity;
            if ($user->isPasswordResetted()) {
                return $this->redirect(['site/reset-password']);
            }
            // Return to default tab logic skipped for brevity
            return $this->goBack();
        }

        $model->password = '';
        $this->layout = 'column1';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    public function actionResetPassword()
    {
        if (Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new ResetPasswordForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $user = Yii::$app->user->identity;
            $user->password = $model->password;
            // Clear the password resetted flag
            $user->flags &= ~User::FLAG_PASSWORD_RESETTED;
            
            // Re-hash the password
            $user->protectPassword();
            
            if ($user->save(false)) {
                Yii::$app->session->setFlash('success', 'New password saved.');
                return $this->goHome();
            }
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    public function actionRecoverPassword()
    {
        $this->layout = 'column1';

        $model = new RecoverPasswordForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $user = $model->getUser();
            if ($user === null) {
                throw new \yii\web\ForbiddenHttpException('Invalid request.');
            }

            // Mint a one-time token tied to this account. The token doubles
            // as the auth key for IdentityInterface::findIdentityByAccessToken
            // so a click on the e-mailed link can authenticate the user once.
            $user->pwd_reset_token = md5(uniqid((string) mt_rand(), true));
            if (!$user->save(false, ['pwd_reset_token'])) {
                throw new \yii\web\ServerErrorHttpException("Couldn't update user info.");
            }

            $loginUrl = \yii\helpers\Url::to(['site/login', 'prt' => $user->pwd_reset_token], true);

            try {
                Yii::$app->mailer->compose()
                    ->setTo($user->email)
                    ->setFrom(['no-reply@' . Yii::$app->request->serverName => Yii::$app->name])
                    ->setSubject('CrashFix Account Password Recovery')
                    ->setTextBody(
                        "This message has been sent because someone requested to recover the lost password\n"
                        . "of your CrashFix account.\n\n"
                        . "IMPORTANT: If you did not request password recovery, please notify your administrator.\n\n"
                        . "If you did request it, follow this link to log in once and choose a new password:\n"
                        . $loginUrl . "\n"
                    )
                    ->send();

                Yii::$app->session->setFlash(
                    'recoverPassword',
                    'An e-mail has been sent with password recovery instructions. Please check your inbox.'
                );
            } catch (\Throwable $e) {
                Yii::error($e->__toString(), 'mail');
                Yii::$app->session->setFlash(
                    'recoverPassword',
                    'There was a problem sending the recovery e-mail. Please contact your administrator.'
                );
            }

            return $this->refresh();
        }

        return $this->render('recoverPassword', ['model' => $model]);
    }

    public function actionAdmin()
    {
        if (!Yii::$app->request->isAjax) {
            return $this->render('admin');
        } else {
            $licenseInfo = Yii::$app->daemon->getLicenseInfo();
            return $this->renderAjax('_licenseInfo', ['licenseInfo' => $licenseInfo]);
        }
    }

    public function actionDaemon()
    {
        return $this->render('daemon');
    }

    public function actionDaemonStatus()
    {
        if (Yii::$app->request->isAjax) {
            $daemonResponse = "";
            $daemonRetCode = Yii::$app->daemon->getDaemonStatus($daemonResponse);
            $list = preg_split('#;#', $daemonResponse);
            return $this->renderAjax('_daemonStatus', ['daemonRetCode' => $daemonRetCode, 'list' => $list]);
        }
    }

    public function actionCheckDaemon()
    {
        $realCheck = false;
        $errorMsg = 'Unspecified error';
        $retCode = Yii::$app->daemon->checkDaemon($realCheck, $errorMsg);
        if ($realCheck && $retCode != \app\components\Daemon::DAEMON_CHECK_OK) {
            return $this->renderAjax('_daemonCheck', ['retCode' => $retCode, 'errorMsg' => $errorMsg]);
        }
    }

    public function actionSetCurProject($proj, $ver)
    {
        Yii::$app->user->setCurProjectId($proj);
        Yii::$app->user->setCurProjectVer($ver);
        return $this->redirect(Yii::$app->request->referrer ?: Yii::$app->homeUrl);
    }
}
