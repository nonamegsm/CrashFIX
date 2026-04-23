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
use app\models\Crashreport;
use app\models\Debuginfo;
use app\models\Processingerror;
use app\components\MiscHelpers;
use yii\data\ActiveDataProvider;

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
                'only' => ['index', 'logout', 'reset-password', 'set-cur-project', 'check-daemon',
                           'admin', 'daemon', 'daemon-status', 'failed', 'failed-retry'],
                'rules' => [
                    [
                        'actions' => ['index', 'logout', 'reset-password', 'set-cur-project', 'check-daemon',
                                      'failed', 'failed-retry'],
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
                    'failed-retry' => ['post'],
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

    /**
     * Show every crash-report and debug-info file in the current
     * project that the daemon could not process. Each row is shown
     * together with the most recent processing-error message so the
     * user can see why it failed without clicking through to the
     * detail page.
     *
     * Project-scoped via the user's current project. Anyone with at
     * least one of the browse permissions sees the page; the two
     * grids are individually gated and degrade to "you don't have
     * access" when neither permission is held.
     */
    public function actionFailed()
    {
        $this->sidebarActiveItem = 'Failed';

        $user      = Yii::$app->user;
        $projectId = (int) $user->getCurProjectId();
        $canCrash  = (bool) $user->can('pperm_browse_some_crash_reports');
        $canDebug  = (bool) $user->can('pperm_browse_some_debug_info');

        // Crash reports with status=Invalid, joined to their most
        // recent processing error via a correlated subquery.
        $crashProvider = null;
        if ($projectId > 0 && $canCrash) {
            $crashQuery = Crashreport::find()
                ->alias('cr')
                ->select([
                    'cr.*',
                    'last_error' => '(SELECT pe.message FROM ' . Processingerror::tableName() . ' pe
                                       WHERE pe.type = :pe_type_cr
                                         AND pe.srcid = cr.id
                                       ORDER BY pe.id DESC
                                       LIMIT 1)',
                ])
                // status=4 is Invalid in the seeded `lookup` table
                // (CrashReportStatus). Crashreport does not yet have
                // STATUS_* constants; using the raw integer with a
                // comment matches the model's existing style (see
                // beforeSave() which writes $this->status = 1).
                ->where(['cr.project_id' => $projectId, 'cr.status' => 4])
                ->params([':pe_type_cr' => Processingerror::TYPE_CRASH_REPORT_ERROR])
                ->orderBy(['cr.received' => SORT_DESC]);

            $crashProvider = new ActiveDataProvider([
                'query'      => $crashQuery,
                'pagination' => ['pageSize' => 50, 'pageParam' => 'cr-page'],
                'sort'       => false,
            ]);
        }

        // Debug-info files with status in {Invalid, Unsupported format}.
        // STATUS_UNSUPPORTED_FORMAT (5) was added in the RFC-001 phase 1
        // migration; reference it via the const so old callers still work
        // if it has not been seeded yet (Lookup item missing != fatal).
        $debugProvider = null;
        if ($projectId > 0 && $canDebug) {
            $debugStatuses = [Debuginfo::STATUS_INVALID];
            if (defined(Debuginfo::class . '::STATUS_UNSUPPORTED_FORMAT')) {
                $debugStatuses[] = Debuginfo::STATUS_UNSUPPORTED_FORMAT;
            }
            $debugQuery = Debuginfo::find()
                ->alias('di')
                ->select([
                    'di.*',
                    'last_error' => '(SELECT pe.message FROM ' . Processingerror::tableName() . ' pe
                                       WHERE pe.type = :pe_type_di
                                         AND pe.srcid = di.id
                                       ORDER BY pe.id DESC
                                       LIMIT 1)',
                ])
                ->where(['di.project_id' => $projectId])
                ->andWhere(['in', 'di.status', $debugStatuses])
                ->params([':pe_type_di' => Processingerror::TYPE_DEBUG_INFO_ERROR])
                ->orderBy(['di.dateuploaded' => SORT_DESC]);

            $debugProvider = new ActiveDataProvider([
                'query'      => $debugQuery,
                'pagination' => ['pageSize' => 50, 'pageParam' => 'di-page'],
                'sort'       => false,
            ]);
        }

        return $this->render('failed', [
            'crashProvider'  => $crashProvider,
            'debugProvider'  => $debugProvider,
            'projectId'      => $projectId,
            'canCrash'       => $canCrash,
            'canDebug'       => $canDebug,
        ]);
    }

    /**
     * POST /site/failed-retry
     *
     * Re-queue a single failed item by flipping its status back to
     * Waiting (1). The daemon picks it up on the next poll cycle.
     * Existing processingerror rows are NOT deleted so the user can
     * still see what went wrong; if the retry succeeds the row is
     * simply rewritten to Ready/Processed and a fresh error appears
     * on the next failure (if any).
     *
     * Inputs (POST):
     *   kind = "crash" | "debug"
     *   id   = integer primary key in the matching table
     *
     * Always redirects back to /site/failed with a flash message.
     */
    public function actionFailedRetry()
    {
        $kind = (string) Yii::$app->request->post('kind', '');
        $id   = (int)    Yii::$app->request->post('id', 0);
        $projectId = (int) Yii::$app->user->getCurProjectId();
        $session = Yii::$app->session;

        if ($id <= 0 || ($kind !== 'crash' && $kind !== 'debug')) {
            $session->setFlash('failed-retry-error', 'Invalid retry request.');
            return $this->redirect(['failed']);
        }

        if ($kind === 'crash') {
            if (!Yii::$app->user->can('pperm_browse_some_crash_reports')) {
                throw new \yii\web\ForbiddenHttpException();
            }
            $row = Crashreport::find()
                ->where(['id' => $id, 'project_id' => $projectId])
                ->one();
            if ($row === null) {
                $session->setFlash('failed-retry-error', "Crash report #{$id} not found in current project.");
                return $this->redirect(['failed']);
            }
            $row->status = 1; // Waiting (CrashReportStatus code 1)
            $row->save(false, ['status']);
            $session->setFlash('failed-retry-success',
                "Crash report #{$id} re-queued. Daemon will retry on the next poll cycle.");
            return $this->redirect(['failed']);
        }

        // kind === 'debug'
        if (!Yii::$app->user->can('pperm_browse_some_debug_info')) {
            throw new \yii\web\ForbiddenHttpException();
        }
        $row = Debuginfo::find()
            ->where(['id' => $id, 'project_id' => $projectId])
            ->one();
        if ($row === null) {
            $session->setFlash('failed-retry-error', "Debug info #{$id} not found in current project.");
            return $this->redirect(['failed']);
        }
        $row->status = Debuginfo::STATUS_WAITING;
        $row->save(false, ['status']);
        $session->setFlash('failed-retry-success',
            "Debug info file #{$id} re-queued. Daemon will retry on the next poll cycle.");
        return $this->redirect(['failed']);
    }
}
