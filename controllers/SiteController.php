<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
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
                           'admin', 'daemon', 'daemon-status', 'daemon-runtime-stats',
                           'failed', 'failed-retry', 'failed-delete'],
                'rules' => [
                    [
                        'actions' => ['index', 'logout', 'reset-password', 'set-cur-project', 'check-daemon',
                                      'failed', 'failed-retry', 'failed-delete'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['admin', 'daemon', 'daemon-status', 'daemon-runtime-stats'],
                        'allow' => true,
                        'roles' => ['gperm_access_admin_panel'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                    'failed-retry'  => ['post'],
                    'failed-delete' => ['post'],
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

    /**
     * Static pages under views/site/pages/{view}.php (Yii1 CViewAction parity).
     *
     * Pretty URL: /site/page/{view}  Legacy query: /site/page?view={view}
     */
    public function actionPage(?string $view = null)
    {
        $view = $view ?? (string) Yii::$app->request->get('view', '');
        $view = trim($view);
        if ($view === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $view)) {
            throw new BadRequestHttpException('Missing or invalid page name.');
        }

        $file = Yii::getAlias('@app/views/site/pages/' . $view . '.php');
        if (!is_file($file)) {
            throw new NotFoundHttpException('Page not found.');
        }

        return $this->render('pages/' . $view);
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
            // Fetch both license + config info in one round-trip so the
            // admin panel surfaces the running daemon's version, web-root,
            // pid, and uptime alongside license details.
            $licenseInfo = Yii::$app->daemon->getLicenseInfo();
            $configInfo  = Yii::$app->daemon->getConfigInfo();
            $webAppVer   = Yii::$app->params['version'] ?? '';
            return $this->renderAjax('_licenseInfo', [
                'licenseInfo' => $licenseInfo,
                'configInfo'  => $configInfo,
                'webAppVer'   => $webAppVer,
            ]);
        }
    }

    public function actionDaemon()
    {
        $this->sidebarActiveItem = 'Administer';
        $this->adminMenuItem     = 'Daemon';

        $dataProvider = new ActiveDataProvider([
            'query' => \app\models\Operation::find()->orderBy(['timestamp' => SORT_DESC]),
            'pagination' => ['pageSize' => 30],
            'sort'       => false,
        ]);
        return $this->render('daemon', ['dataProvider' => $dataProvider]);
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

    /**
     * AJAX-only JSON endpoint that powers the Live Runtime Statistics
     * panel on /site/daemon. Mirrors the Yii1 SiteController::
     * actionDaemonRuntimeStats from CrashFIX php8-compat. Built
     * entirely from tbl_operation + tbl_crashreport so it doesn't
     * need a daemon TCP roundtrip - cheap to poll every few seconds.
     */
    public function actionDaemonRuntimeStats()
    {
        if (!Yii::$app->request->isAjax) {
            return '';
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        $db  = Yii::$app->db;
        $now = time();

        // Throughput windows (operations dispatched in last 5 / 15 / 60 min)
        $throughput = $db->createCommand(
            "SELECT
                SUM(CASE WHEN timestamp >= :t300  THEN 1 ELSE 0 END) AS last_5m,
                SUM(CASE WHEN timestamp >= :t900  THEN 1 ELSE 0 END) AS last_15m,
                SUM(CASE WHEN timestamp >= :t3600 THEN 1 ELSE 0 END) AS last_60m
             FROM {{%operation}}",
            [
                ':t300'  => $now - 300,
                ':t900'  => $now - 900,
                ':t3600' => $now - 3600,
            ]
        )->queryOne();

        // Status mix in the last hour
        $statusRows = $db->createCommand(
            "SELECT status, COUNT(*) AS n
             FROM {{%operation}}
             WHERE timestamp >= :since
             GROUP BY status",
            [':since' => $now - 3600]
        )->queryAll();

        // Operation status codes (from tbl_lookup OperationStatus seed):
        // 1=Started, 2=Succeeded, 3=Failed
        $statusMix = ['started' => 0, 'succeeded' => 0, 'failed' => 0];
        foreach ($statusRows as $r) {
            switch ((int) $r['status']) {
                case 1: $statusMix['started']   = (int) $r['n']; break;
                case 2: $statusMix['succeeded'] = (int) $r['n']; break;
                case 3: $statusMix['failed']    = (int) $r['n']; break;
            }
        }
        $totalLastHour = $statusMix['started'] + $statusMix['succeeded'] + $statusMix['failed'];
        $succRate = ($statusMix['succeeded'] + $statusMix['failed']) > 0
            ? round(100.0 * $statusMix['succeeded'] / ($statusMix['succeeded'] + $statusMix['failed']), 1)
            : null;

        // Optype breakdown (1=ImportPdb, 2=ProcessCrashReport, 3=DeleteDebugInfo)
        $typeRows = $db->createCommand(
            "SELECT optype, status, COUNT(*) AS n
             FROM {{%operation}}
             WHERE timestamp >= :since
             GROUP BY optype, status",
            [':since' => $now - 3600]
        )->queryAll();

        $typeNames = [
            1 => 'Import PDB',
            2 => 'Process crash report',
            3 => 'Delete debug info',
        ];
        $byType = [];
        foreach ($typeNames as $tname) {
            $byType[$tname] = ['ok' => 0, 'failed' => 0, 'started' => 0, 'total' => 0];
        }
        foreach ($typeRows as $r) {
            $tid = (int) $r['optype'];
            if (!isset($typeNames[$tid])) continue;
            $tname = $typeNames[$tid];
            $n = (int) $r['n'];
            $byType[$tname]['total'] += $n;
            switch ((int) $r['status']) {
                case 1: $byType[$tname]['started'] += $n; break;
                case 2: $byType[$tname]['ok']      += $n; break;
                case 3: $byType[$tname]['failed']  += $n; break;
            }
        }

        // Currently in-flight operations
        $runningRows = $db->createCommand(
            "SELECT id, cmdid, optype, timestamp, operand1
             FROM {{%operation}}
             WHERE status = 1
             ORDER BY timestamp ASC
             LIMIT 50"
        )->queryAll();

        $running = [];
        foreach ($runningRows as $r) {
            $path = (string) $r['operand1'];
            $file = basename($path) ?: $path;
            $tid  = (int) $r['optype'];
            $running[] = [
                'id'        => (int) $r['id'],
                'cmdid'     => (string) $r['cmdid'],
                'optype'    => $typeNames[$tid] ?? ('opcode ' . $tid),
                'started'   => (int) $r['timestamp'],
                'elapsed_s' => max(0, $now - (int) $r['timestamp']),
                'file'      => $file,
            ];
        }

        // Recent failures (last 10)
        $recentFailRows = $db->createCommand(
            "SELECT id, cmdid, optype, timestamp, operand1
             FROM {{%operation}}
             WHERE status = 3
             ORDER BY id DESC
             LIMIT 10"
        )->queryAll();

        $recentFailures = [];
        foreach ($recentFailRows as $r) {
            $path = (string) $r['operand1'];
            $file = basename($path) ?: $path;
            $tid  = (int) $r['optype'];
            $recentFailures[] = [
                'id'      => (int) $r['id'],
                'optype'  => $typeNames[$tid] ?? ('opcode ' . $tid),
                'when'    => date('Y-m-d H:i:s', (int) $r['timestamp']),
                'ago_s'   => max(0, $now - (int) $r['timestamp']),
                'file'    => $file,
            ];
        }

        // Lifetime crash-report processing progress.
        // Crashreport status codes: 1=Waiting, 2=Processing, 3=Processed, 4=Invalid
        $crashCounts = $db->createCommand(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS processed,
                SUM(CASE WHEN status IN (3, 4) THEN 1 ELSE 0 END) AS done
             FROM {{%crashreport}}"
        )->queryOne();
        $crashTotal     = (int) ($crashCounts['total']     ?? 0);
        $crashProcessed = (int) ($crashCounts['processed'] ?? 0);
        $crashDone      = (int) ($crashCounts['done']      ?? 0);
        $processedPct   = $crashTotal > 0 ? round(100.0 * $crashProcessed / $crashTotal, 1) : null;
        $donePct        = $crashTotal > 0 ? round(100.0 * $crashDone      / $crashTotal, 1) : null;

        return [
            'now'        => $now,
            'throughput' => [
                'per_5m'       => (int) $throughput['last_5m'],
                'per_15m'      => (int) $throughput['last_15m'],
                'per_60m'      => (int) $throughput['last_60m'],
                'rate_per_min' => round((int) $throughput['last_60m'] / 60.0, 2),
            ],
            'last_hour' => [
                'total'       => $totalLastHour,
                'succeeded'   => $statusMix['succeeded'],
                'failed'      => $statusMix['failed'],
                'in_flight'   => $statusMix['started'],
                'success_pct' => $succRate,
            ],
            'crash_lifetime' => [
                'total'         => $crashTotal,
                'processed'     => $crashProcessed,
                'done'          => $crashDone,
                'processed_pct' => $processedPct,
                'done_pct'      => $donePct,
                'pending'       => max(0, $crashTotal - $crashDone),
            ],
            'by_type'         => $byType,
            'running'         => $running,
            'running_count'   => count($running),
            'recent_failures' => $recentFailures,
        ];
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

        // Free-text search params (one per grid, distinct names so
        // search/sort/page on one section never stomps the other).
        $req    = Yii::$app->request;
        $crashQ = trim((string) $req->get('cr-q', ''));
        $debugQ = trim((string) $req->get('di-q', ''));

        $peTbl   = Processingerror::tableName();
        // Cast the type constants to int and inline them directly into
        // the correlated subquery string. They are NOT user input -
        // they are class constants we own - so the cast is sufficient
        // injection guard. We cannot use named bind params for these
        // because Yii2's ActiveDataProvider::getTotalCount() builds a
        // fresh SELECT COUNT(*) that drops the select clause but
        // keeps the bound params, and PDO then errors with
        // SQLSTATE[HY093] "number of bound variables does not match
        // number of tokens".
        $peTypeCr = (int) Processingerror::TYPE_CRASH_REPORT_ERROR;
        $peTypeDi = (int) Processingerror::TYPE_DEBUG_INFO_ERROR;

        // ---- Failed crash reports -------------------------------------
        $crashProvider = null;
        if ($projectId > 0 && $canCrash) {
            $crashQuery = Crashreport::find()
                ->alias('cr')
                ->select([
                    'cr.*',
                    'last_error' => "(SELECT pe.message FROM {$peTbl} pe
                                       WHERE pe.type = {$peTypeCr}
                                         AND pe.srcid = cr.id
                                       ORDER BY pe.id DESC
                                       LIMIT 1)",
                ])
                // status=4 is Invalid in the seeded `lookup` table
                // (CrashReportStatus). Crashreport does not yet have
                // STATUS_* constants; using the raw integer with a
                // comment matches the model's existing style (see
                // beforeSave() which writes $this->status = 1).
                ->where(['cr.project_id' => $projectId, 'cr.status' => 4]);

            // Free-text filter: matches filename / crashguid / any
            // historical processingerror.message via EXISTS subquery.
            // EXISTS keeps it as a single SQL pass and avoids HAVING-
            // vs-WHERE alias scoping problems with `last_error`.
            if ($crashQ !== '') {
                $crashQuery->andWhere([
                    'or',
                    ['like', 'cr.srcfilename', $crashQ],
                    ['like', 'cr.crashguid',   $crashQ],
                    ['exists', (new \yii\db\Query())
                        ->from($peTbl . ' pe2')
                        ->where('pe2.srcid = cr.id')
                        ->andWhere(['pe2.type' => Processingerror::TYPE_CRASH_REPORT_ERROR])
                        ->andWhere(['like', 'pe2.message', $crashQ])
                    ],
                ]);
            }

            $crashProvider = new ActiveDataProvider([
                'query'      => $crashQuery,
                'pagination' => ['pageSize' => 50, 'pageParam' => 'cr-page'],
                'sort'       => [
                    'sortParam'    => 'cr-sort',
                    'defaultOrder' => ['received' => SORT_DESC],
                    'attributes'   => [
                        'id'          => ['asc' => ['cr.id'          => SORT_ASC], 'desc' => ['cr.id'          => SORT_DESC]],
                        'srcfilename' => ['asc' => ['cr.srcfilename' => SORT_ASC], 'desc' => ['cr.srcfilename' => SORT_DESC]],
                        'received'    => ['asc' => ['cr.received'    => SORT_ASC], 'desc' => ['cr.received'    => SORT_DESC]],
                        'filesize'    => ['asc' => ['cr.filesize'    => SORT_ASC], 'desc' => ['cr.filesize'    => SORT_DESC]],
                    ],
                ],
            ]);
        }

        // ---- Failed debug-info files ----------------------------------
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
                    'last_error' => "(SELECT pe.message FROM {$peTbl} pe
                                       WHERE pe.type = {$peTypeDi}
                                         AND pe.srcid = di.id
                                       ORDER BY pe.id DESC
                                       LIMIT 1)",
                ])
                ->where(['di.project_id' => $projectId])
                ->andWhere(['in', 'di.status', $debugStatuses]);

            if ($debugQ !== '') {
                $debugQuery->andWhere([
                    'or',
                    ['like', 'di.filename', $debugQ],
                    ['like', 'di.guid',     $debugQ],
                    ['exists', (new \yii\db\Query())
                        ->from($peTbl . ' pe2')
                        ->where('pe2.srcid = di.id')
                        ->andWhere(['pe2.type' => Processingerror::TYPE_DEBUG_INFO_ERROR])
                        ->andWhere(['like', 'pe2.message', $debugQ])
                    ],
                ]);
            }

            $debugProvider = new ActiveDataProvider([
                'query'      => $debugQuery,
                'pagination' => ['pageSize' => 50, 'pageParam' => 'di-page'],
                'sort'       => [
                    'sortParam'    => 'di-sort',
                    'defaultOrder' => ['dateuploaded' => SORT_DESC],
                    'attributes'   => [
                        'id'           => ['asc' => ['di.id'           => SORT_ASC], 'desc' => ['di.id'           => SORT_DESC]],
                        'filename'     => ['asc' => ['di.filename'     => SORT_ASC], 'desc' => ['di.filename'     => SORT_DESC]],
                        'status'       => ['asc' => ['di.status'       => SORT_ASC], 'desc' => ['di.status'       => SORT_DESC]],
                        'dateuploaded' => ['asc' => ['di.dateuploaded' => SORT_ASC], 'desc' => ['di.dateuploaded' => SORT_DESC]],
                        'filesize'     => ['asc' => ['di.filesize'     => SORT_ASC], 'desc' => ['di.filesize'     => SORT_DESC]],
                    ],
                ],
            ]);
        }

        return $this->render('failed', [
            'crashProvider' => $crashProvider,
            'debugProvider' => $debugProvider,
            'projectId'     => $projectId,
            'canCrash'      => $canCrash,
            'canDebug'      => $canDebug,
            'crashQ'        => $crashQ,
            'debugQ'        => $debugQ,
        ]);
    }

    /**
     * POST /site/failed-retry
     *
     * Re-queue failed items by flipping their status back to Waiting
     * (1). The daemon picks them up on the next poll cycle. Existing
     * tbl_processingerror rows are NOT touched so the user can still
     * see the previous failure reason; on a successful retry the
     * status moves on to Ready and a fresh error appears only if it
     * fails again.
     *
     * Inputs (POST):
     *   kind = "crash" | "debug"
     *   ONE of:
     *     id   = integer  -- single-row retry
     *     ids  = int[]    -- bulk retry of selected rows
     *     all  = "1"      -- bulk retry of every failed row in the
     *                        current project (optionally filtered
     *                        by `q` to match the search box)
     *   q    = string    -- optional, only meaningful with all=1
     *
     * Implemented as a single SQL UPDATE so even thousands of rows
     * complete in a few milliseconds. Caller is redirected back to
     * /site/failed with a flash message.
     */
    public function actionFailedRetry()
    {
        $req     = Yii::$app->request;
        $session = Yii::$app->session;
        $kind    = (string) $req->post('kind', '');
        $id      = (int)    $req->post('id', 0);
        $ids     = (array)  $req->post('ids', []);
        $all     = (string) $req->post('all', '') === '1';
        $q       = trim((string) $req->post('q', ''));
        $projectId = (int) Yii::$app->user->getCurProjectId();

        if ($projectId <= 0 || ($kind !== 'crash' && $kind !== 'debug')) {
            $session->setFlash('failed-retry-error', 'Invalid retry request.');
            return $this->redirect(['failed']);
        }

        $bulk = $this->buildFailedBulkContext($kind, $projectId, $id, $ids, $all, $q);
        if ($bulk['error'] !== null) {
            $session->setFlash('failed-retry-error', $bulk['error']);
            return $this->redirect(['failed']);
        }

        // Single SQL UPDATE for the matching rows. Atomic, idempotent
        // (already-Waiting rows just get rewritten with the same value),
        // and cheap even on large filtered sets.
        $newStatus = ($kind === 'crash') ? 1 /*Waiting*/ : Debuginfo::STATUS_WAITING;
        $count = $bulk['table']::updateAll(
            ['status' => $newStatus],
            $bulk['where'],
            $bulk['params']
        );

        $session->setFlash('failed-retry-success',
            "Re-queued {$count} " . ($kind === 'crash' ? 'crash report' : 'debug info file')
            . ($count === 1 ? '' : 's') .
            ". Daemon will retry on the next poll cycle.");
        return $this->redirect($this->failedReturnUrl($req));
    }

    /**
     * POST /site/failed-delete
     *
     * Permanently delete failed items. Uses ActiveRecord delete()
     * (not raw DELETE) so afterDelete() hooks fire and clean up the
     * on-disk files referenced by Storage. Capped at MAX rows per
     * request; if more rows match the user is told to click again.
     *
     * Inputs identical to actionFailedRetry().
     */
    public function actionFailedDelete()
    {
        $req     = Yii::$app->request;
        $session = Yii::$app->session;
        $kind    = (string) $req->post('kind', '');
        $id      = (int)    $req->post('id', 0);
        $ids     = (array)  $req->post('ids', []);
        $all     = (string) $req->post('all', '') === '1';
        $q       = trim((string) $req->post('q', ''));
        $projectId = (int) Yii::$app->user->getCurProjectId();

        if ($projectId <= 0 || ($kind !== 'crash' && $kind !== 'debug')) {
            $session->setFlash('failed-retry-error', 'Invalid delete request.');
            return $this->redirect(['failed']);
        }

        $bulk = $this->buildFailedBulkContext($kind, $projectId, $id, $ids, $all, $q);
        if ($bulk['error'] !== null) {
            $session->setFlash('failed-retry-error', $bulk['error']);
            return $this->redirect(['failed']);
        }

        // Cap per click. AR delete fires per-row hooks that unlink
        // files; processing 5000 of those in one request risks PHP
        // max_execution_time / FastCGI timeouts. The page surfaces
        // the "X remaining" message so the admin can just click
        // again to drain the rest.
        $cap = 500;
        $rows = $bulk['table']::find()
            ->where($bulk['where'], $bulk['params'])
            ->limit($cap + 1)
            ->all();

        $hasMore = count($rows) > $cap;
        if ($hasMore) {
            array_pop($rows);
        }

        $deleted = 0;
        $errors  = 0;
        foreach ($rows as $row) {
            try {
                if ($row->delete()) {
                    $deleted++;
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Yii::warning('failed-delete row ' . $row->id . ': ' . $e->getMessage(), 'site');
            }
        }

        $kindLabel = $kind === 'crash' ? 'crash report' : 'debug info file';
        $msg = "Deleted {$deleted} " . $kindLabel . ($deleted === 1 ? '' : 's') . '.';
        if ($errors > 0) {
            $msg .= " {$errors} could not be deleted (see logs).";
        }
        if ($hasMore) {
            $msg .= " More rows match the filter; click Delete again to continue.";
        }
        $session->setFlash($errors === 0 ? 'failed-retry-success' : 'failed-retry-error', $msg);
        return $this->redirect($this->failedReturnUrl($req));
    }

    /**
     * Shared validation + WHERE-clause builder for the bulk retry /
     * delete endpoints. Returns
     *   ['error' => string|null, 'table' => class, 'where' => array,
     *    'params' => array]
     *
     * Three input modes supported:
     *   single  -> id   given      -> WHERE id = :id
     *   bulk    -> ids[] given     -> WHERE id IN (...)
     *   all     -> all=1 given     -> WHERE status IN (failed-statuses)
     *                                  + free-text filter via EXISTS
     *                                    on tbl_processingerror.message
     *                                    matching the same shape as
     *                                    actionFailed().
     *
     * Project-scope and permission checks are enforced here so both
     * endpoints share identical authorisation logic.
     */
    private function buildFailedBulkContext($kind, $projectId, $id, array $ids, $all, $q)
    {
        $user = Yii::$app->user;

        if ($kind === 'crash') {
            if (!$user->can('pperm_browse_some_crash_reports')) {
                throw new \yii\web\ForbiddenHttpException();
            }
            $tableClass    = Crashreport::class;
            $statusValues  = [4]; // Invalid
            $fileCol       = 'srcfilename';
            $guidCol       = 'crashguid';
            $peType        = Processingerror::TYPE_CRASH_REPORT_ERROR;
        } else {
            if (!$user->can('pperm_browse_some_debug_info')) {
                throw new \yii\web\ForbiddenHttpException();
            }
            $tableClass    = Debuginfo::class;
            $statusValues  = [Debuginfo::STATUS_INVALID];
            if (defined(Debuginfo::class . '::STATUS_UNSUPPORTED_FORMAT')) {
                $statusValues[] = Debuginfo::STATUS_UNSUPPORTED_FORMAT;
            }
            $fileCol = 'filename';
            $guidCol = 'guid';
            $peType  = Processingerror::TYPE_DEBUG_INFO_ERROR;
        }

        // Single
        if ($id > 0) {
            return [
                'error'  => null,
                'table'  => $tableClass,
                'where'  => 'id = :id AND project_id = :pid AND status IN (' .
                            implode(',', array_map('intval', $statusValues)) . ')',
                'params' => [':id' => (int) $id, ':pid' => $projectId],
            ];
        }

        // Bulk by selection
        if (!empty($ids)) {
            $cleanIds = [];
            foreach ($ids as $v) {
                $v = (int) $v;
                if ($v > 0) $cleanIds[] = $v;
            }
            if (empty($cleanIds)) {
                return ['error' => 'No items selected.', 'table' => null, 'where' => null, 'params' => null];
            }
            return [
                'error'  => null,
                'table'  => $tableClass,
                'where'  => ['and',
                    ['in', 'id', $cleanIds],
                    ['project_id' => $projectId],
                    ['in', 'status', $statusValues],
                ],
                'params' => [],
            ];
        }

        // All matching (optionally filtered by q)
        if ($all) {
            $peTbl = Processingerror::tableName();
            $where = ['and',
                ['project_id' => $projectId],
                ['in', 'status', $statusValues],
            ];
            if ($q !== '') {
                $where[] = ['or',
                    ['like', $fileCol, $q],
                    ['like', $guidCol, $q],
                    ['exists', (new \yii\db\Query())
                        ->from($peTbl . ' pe2')
                        ->where('pe2.srcid = ' . call_user_func([$tableClass, 'tableName']) . '.id')
                        ->andWhere(['pe2.type' => $peType])
                        ->andWhere(['like', 'pe2.message', $q])
                    ],
                ];
            }
            return [
                'error'  => null,
                'table'  => $tableClass,
                'where'  => $where,
                'params' => [],
            ];
        }

        return ['error' => 'No items targeted.', 'table' => null, 'where' => null, 'params' => null];
    }

    /**
     * Redirect target after a bulk action: try to preserve the
     * filter / sort / page so the user lands back on the same view.
     * Falls back to bare /site/failed if no return URL was POSTed.
     */
    private function failedReturnUrl($req)
    {
        $back = (string) $req->post('return', '');
        if ($back !== '' && strncmp($back, '/', 1) === 0
            && strpos($back, "\n") === false && strpos($back, "\r") === false) {
            return $back;
        }
        return ['failed'];
    }
}
