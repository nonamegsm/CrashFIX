<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use yii\web\UploadedFile;
use app\models\CrashReport;
use app\models\CrashreportSearch;
use app\models\MailQueue;
use app\models\Thread;
use app\models\Stackframe;
use app\models\Project;

class CrashReportController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'CrashReports';
    public $adminMenuItem;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['upload-external'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => [
                            'index', 'view', 'extract-file', 'download', 'view-screenshot', 
                            'view-screenshot-thumbnail', 'view-video', 'upload-stat', 
                            'version-dist', 'os-version-dist', 'geo-location-dist', 
                            'process-again', 'reprocess-multiple', 'reprocess-all', 
                            'delete', 'delete-multiple', 'upload-file'
                        ],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'delete-multiple' => ['POST'],
                    'process-again' => ['POST'],
                    'reprocess-multiple' => ['POST'],
                    'reprocess-all' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView($id, $tab = 'Summary', $thread = null)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        $validTabs = ['Summary', 'CustomProps', 'Screenshots', 'Videos', 'Threads', 'Modules', 'Files'];
        $activeItem = in_array($tab, $validTabs) ? $tab : 'Summary';

        $threadModel = null;
        $stackTraceProvider = null;
        if ($thread !== null) {
            $threadModel = Thread::findOne((int)$thread);
            if ($threadModel === null) {
                throw new ForbiddenHttpException('Unexpected parameter.');
            }

            $stackTraceProvider = new ActiveDataProvider([
                'query' => Stackframe::find()->where(['thread_id' => (int)$thread])->orderBy('id ASC'),
                'pagination' => false,
            ]);
        }

        // Search methods assumed to be on the model from legacy code or needs implementation in Yii2 models
        $customProps = method_exists($model, 'searchCustomProps') ? $model->searchCustomProps() : null;
        $screenshots = method_exists($model, 'searchScreenshots') ? $model->searchScreenshots() : null;
        $videos = method_exists($model, 'searchVideos') ? $model->searchVideos() : null;
        $modules = method_exists($model, 'searchModules') ? $model->searchModules() : null;
        $threads = method_exists($model, 'searchThreads') ? $model->searchThreads() : null;

        return $this->render('view', [
            'model' => $model,
            'activeItem' => $activeItem,
            'thread' => (int)$thread,
            'threadModel' => $threadModel,
            'stackTrace' => $stackTraceProvider,
            'customProps' => $customProps,
            'screenshots' => $screenshots,
            'videos' => $videos,
            'modules' => $modules,
            'threads' => $threads,
        ]);
    }

    public function actionDownload($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);
        $model->dumpFileAttachmentContent();
    }

    public function actionExtractFile($name, $rpt)
    {
        $model = $this->findModel($rpt);
        $this->checkAuthorization($model);
        $model->dumpFileItemContent($name, true);
    }

    public function actionViewScreenshot($name, $rpt)
    {
        $model = $this->findModel($rpt);
        $this->checkAuthorization($model);
        $model->dumpFileItemContent($name, false);
    }

    public function actionViewScreenshotThumbnail($name, $rpt)
    {
        $model = $this->findModel($rpt);
        $this->checkAuthorization($model);
        $model->dumpScreenshotThumbnail($name);
    }

    public function actionViewVideo($name, $rpt)
    {
        $model = $this->findModel($rpt);
        $this->checkAuthorization($model);
        $model->dumpFileItemContent($name, false);
    }

    public function actionProcessAgain()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            if (method_exists($model, 'resetStatus')) $model->resetStatus();
            return $this->redirect(['view', 'id' => $model->id]);
        }
        throw new \yii\web\BadRequestHttpException('Invalid request');
    }

    public function actionDelete()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            $model->delete();
            return $this->redirect(['index']);
        }
        throw new \yii\web\BadRequestHttpException('Invalid request');
    }

    public function actionDeleteMultiple($groupid = null)
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        foreach ($deleteRows as $id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            $model->delete();
        }

        if ($groupid) {
            return $this->redirect(['crash-group/view', 'id' => (int)$groupid]);
        }
        return $this->redirect(['index']);
    }

    public function actionReprocessMultiple($groupid = null)
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        foreach ($deleteRows as $id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            if (method_exists($model, 'canResetStatus') && $model->canResetStatus()) {
                if (method_exists($model, 'resetStatus')) $model->resetStatus();
            }
        }

        if ($groupid) {
            return $this->redirect(['crash-group/view', 'id' => (int)$groupid]);
        }
        return $this->redirect(['index']);
    }

    public function actionReprocessAll($groupid = null)
    {
        $this->checkAuthorization(null);
        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId == false) throw new \yii\web\BadRequestHttpException('Invalid request.');

        $curProjVer = Yii::$app->user->getCurProjectVer();

        $query = CrashReport::find()->where(['project_id' => $projectId]);
        if ($curProjVer != -1) { // -1 = PROJ_VER_ALL sentinel (see WebUser::getCurProjectVer)
            $query->andWhere(['appversion_id' => $curProjVer]);
        }
        if ($groupid) {
            $query->andWhere(['groupid' => (int)$groupid]);
        }

        foreach ($query->all() as $model) {
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            if (method_exists($model, 'canResetStatus') && $model->canResetStatus()) {
                if (method_exists($model, 'resetStatus')) $model->resetStatus();
            }
        }

        if ($groupid) {
            return $this->redirect(['crash-group/view', 'id' => (int)$groupid]);
        }
        return $this->redirect(['index']);
    }

    /**
     * Permanently delete every crash report belonging to the user's
     * current project + version selection.
     *
     * Mirror of Yii1 CrashReportController::actionDeleteAllByVer.
     * When the user's selected version is the ALL sentinel (-1),
     * EVERY report in the project is deleted - the same dangerous
     * semantic the Yii1 endpoint exposes. The dropdown's confirm()
     * dialog warns about this.
     *
     * Uses ActiveRecord ::delete() so afterDelete() hooks fire and
     * clean up the on-disk .zip files via the Storage component.
     *
     * Time / memory limits are bumped because the operation is
     * intentionally background-ish and may chew through thousands
     * of rows on big projects.
     */
    public function actionDeleteAllByVer()
    {
        $this->checkAuthorization(null);
        $projectId = (int) Yii::$app->user->getCurProjectId();
        if ($projectId <= 0) {
            throw new \yii\web\BadRequestHttpException('Invalid request.');
        }
        $curProjVer = Yii::$app->user->getCurProjectVer();

        $query = CrashReport::find()->where(['project_id' => $projectId]);
        if ($curProjVer != -1) {
            $query->andWhere(['appversion_id' => $curProjVer]);
        }

        @set_time_limit(60 * 60);
        @ini_set('memory_limit', '-1');

        foreach ($query->each(200) as $model) {
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            if (!$model->delete()) {
                throw new \yii\web\ServerErrorHttpException(
                    'Could not delete crash report #' . $model->id);
            }
        }
        return $this->redirect(['index']);
    }

    /**
     * Permanently delete every crash report whose appversion_id is
     * STRICTLY less than the user's current project version, AND
     * delete those AppVersion rows themselves.
     *
     * Mirror of Yii1 CrashReportController::actionDeleteAllBeforeVer.
     * Refuses when the current selection is "All versions" because
     * "before all" is meaningless and would risk wiping the entire
     * project's history with no backstop.
     */
    public function actionDeleteAllBeforeVer()
    {
        $this->checkAuthorization(null);
        $projectId  = (int) Yii::$app->user->getCurProjectId();
        $curProjVer = Yii::$app->user->getCurProjectVer();

        if ($projectId <= 0 || $curProjVer == -1) {
            throw new \yii\web\BadRequestHttpException(
                'Invalid request: pick a specific version (not All) for "before" deletion.');
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '-1');

        // 1. Reports with an older version
        $reports = CrashReport::find()
            ->where(['project_id' => $projectId])
            ->andWhere(['<', 'appversion_id', (int) $curProjVer]);
        foreach ($reports->each(200) as $model) {
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            if (!$model->delete()) {
                throw new \yii\web\ServerErrorHttpException(
                    'Could not delete crash report #' . $model->id);
            }
        }

        // 2. The AppVersion rows themselves (mirrors Yii1 behaviour)
        if (class_exists(\app\models\Appversion::class)) {
            $versions = \app\models\Appversion::find()
                ->where(['project_id' => $projectId])
                ->andWhere(['<', 'id', (int) $curProjVer]);
            foreach ($versions->each(200) as $v) {
                if (!$v->delete()) {
                    throw new \yii\web\ServerErrorHttpException(
                        'Could not delete app version #' . $v->id);
                }
            }
        }

        return $this->redirect(['index']);
    }

    /**
     * Copy every crash-report .zip belonging to the user's current
     * project + version into a single dated directory under
     * /data/packedReports/, then enqueue an email to the user when
     * done.
     *
     * Mirror of Yii1 CrashReportController::actionPackAllByVer. Used
     * by support / dev teams to ship a snapshot of one version's
     * reports off the server for offline analysis.
     */
    public function actionPackAllByVer()
    {
        $this->checkAuthorization(null);
        $projectId = (int) Yii::$app->user->getCurProjectId();
        if ($projectId <= 0) {
            throw new \yii\web\BadRequestHttpException('Invalid request.');
        }
        $curProjVer = Yii::$app->user->getCurProjectVer();
        $verLabel   = $curProjVer == -1 ? 'all' : (string) $curProjVer;

        $query = CrashReport::find()->where(['project_id' => $projectId]);
        if ($curProjVer != -1) {
            $query->andWhere(['appversion_id' => $curProjVer]);
        }

        $name    = 'pack_' . date('Ymd_His') . '_p' . $projectId . '_v' . $verLabel;
        $baseDir = Yii::getAlias('@app') . '/data/packedReports';
        $dirName = $baseDir . '/' . $name;
        if (!is_dir($dirName) && !@mkdir($dirName, 0775, true)) {
            $err = error_get_last();
            throw new \yii\web\ServerErrorHttpException(
                'Could not create pack directory: ' . ($err['message'] ?? $dirName));
        }

        @set_time_limit(60 * 60);
        @ini_set('memory_limit', '-1');

        $storage = Yii::$app->storage;
        $copied  = 0;
        $missing = 0;
        foreach ($query->each(200) as $model) {
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            $src = $storage->crashReportPath($projectId, (int) $model->id);
            $dst = $dirName . '/' . (int) $model->id . '.zip';
            if (!is_file($src)) { $missing++; continue; }
            if (@copy($src, $dst)) {
                $copied++;
            } else {
                $err = error_get_last();
                throw new \yii\web\ServerErrorHttpException(
                    'Could not copy crash report #' . $model->id .
                    ' to pack: ' . ($err['message'] ?? ''));
            }
        }

        // Notify the operator via the existing mail queue. Failure
        // here is non-fatal - the pack itself is on disk.
        try {
            $user = Yii::$app->user->identity;
            $email = $user && property_exists($user, 'email') ? (string) $user->email : '';
            if ($email !== '') {
                $body = "Pack {$name} is ready.\n"
                      . "Location: {$dirName}\n"
                      . "Files copied: {$copied}\n"
                      . ($missing > 0 ? "Missing on disk: {$missing}\n" : '');
                $mq = new MailQueue();
                $mq->recipient     = $email;
                $mq->email_subject = 'Pack ' . $name;
                $mq->email_headers = '';
                $mq->email_body    = $body;
                $mq->status        = 1; // pending
                $mq->create_time   = time();
                $mq->save(false);
            }
        } catch (\Throwable $e) {
            Yii::warning('packAllByVer mail enqueue failed: ' . $e->getMessage(), 'crashreport');
        }

        Yii::$app->session->setFlash('success',
            "Packed {$copied} crash reports to {$name}" .
            ($missing > 0 ? " ({$missing} missing on disk)" : '') . '.');
        return $this->redirect(['index']);
    }

    public function actionIndex()
    {
        $this->checkAuthorization(null);

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId == false) throw new \yii\web\BadRequestHttpException('Invalid request.');

        $searchModel  = new CrashreportSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            (int) $projectId,
            (int) Yii::$app->user->getCurProjectVer()
        );

        return $this->render('index', [
            'model'        => $searchModel,
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionUploadFile()
    {
        $this->checkAuthorization(null, 'pperm_manage_crash_reports');
        $model = new CrashReport();
        $model->project_id = (int) Yii::$app->user->getCurProjectId();

        if ($model->load(Yii::$app->request->post())) {
            $model->fileAttachment = UploadedFile::getInstance($model, 'fileAttachment');
            if ($model->save()) {
                $model->persistAttachment();
                Yii::$app->session->setFlash('success', "Crash report #{$model->id} uploaded.");
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('uploadFile', ['model' => $model]);
    }

    public function actionUploadExternal()
    {
        $report = new CrashReport();
        $transaction = Yii::$app->db->beginTransaction();

        try {
            $report->crashrptver = Yii::$app->request->post('crashrptver');
            $report->crashguid   = Yii::$app->request->post('crashguid');

            $appName = Yii::$app->request->post('appname');
            if ($appName) {
                $project = Project::findOne(['name' => $appName]);
                if ($project) {
                    $report->project_id = $project->id;
                }
            }

            $report->fileAttachment      = UploadedFile::getInstanceByName('crashrpt');
            $report->appversionStr       = Yii::$app->request->post('appversion');
            $report->md5                 = Yii::$app->request->post('md5');
            $report->emailfrom           = Yii::$app->request->post('emailfrom');
            $report->description         = Yii::$app->request->post('description');
            $report->exceptionmodule     = Yii::$app->request->post('exceptionmodule');
            $report->exceptionmodulebase = Yii::$app->request->post('exceptionmodulebase');
            $report->exceptionaddress    = Yii::$app->request->post('exceptionaddress');

            if ($report->save()) {
                $report->persistAttachment();
                $transaction->commit();
            } else {
                $transaction->rollBack();
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            $report->addError('crashrpt', 'Exception caught: ' . $e->getMessage());
        }

        return $this->renderPartial('_upload', ['model' => $report]);
    }

    /**
     * JSON: crash report uploads-per-day for the trailing $period days.
     * Consumed by Chart.js on the Digest page.
     *
     * The legacy $w/$h params are accepted for URL backward-compat but
     * ignored — the client sizes the canvas via CSS.
     */
    public function actionUploadStat($period = 7, $w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->crashReportUploadDynamics(
            (int) Yii::$app->user->getCurProjectId(),
            (int) $period,
            (int) Yii::$app->user->getCurProjectVer()
        );
    }

    public function actionVersionDist($w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->crashReportVersionDistribution(
            (int) Yii::$app->user->getCurProjectId()
        );
    }

    public function actionOsVersionDist($w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->crashReportOsDistribution(
            (int) Yii::$app->user->getCurProjectId(),
            (int) Yii::$app->user->getCurProjectVer()
        );
    }

    public function actionGeoLocationDist($w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->crashReportGeoDistribution(
            (int) Yii::$app->user->getCurProjectId(),
            (int) Yii::$app->user->getCurProjectVer()
        );
    }

    protected function findModel($id)
    {
        if (($model = CrashReport::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    protected function checkAuthorization($model, $permission = 'pperm_browse_crash_reports')
    {
        if ($model === null) {
            $projectId = Yii::$app->user->getCurProjectId();
            if ($projectId == false) return;
        } else {
            $projectId = $model->project_id;
        }

        if (!Yii::$app->user->can($permission, ['project_id' => $projectId])) {
            throw new ForbiddenHttpException('You are not authorized to perform this action.');
        }
    }
}
