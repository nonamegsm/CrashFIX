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
use app\models\DebugInfo;
use app\models\DebuginfoSearch;

class DebugInfoController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'DebugInfo';
    public $adminMenuItem;
    const ACTION_CHECK = 1;
    const ACTION_UPLOAD = 2;

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
                        'actions' => ['index', 'view', 'download', 'upload-stat', 'delete', 'delete-multiple', 'upload-file'],
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
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $this->checkAuthorization(null);

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId == false) {
            throw new \yii\web\BadRequestHttpException('Invalid request.');
        }

        $searchModel  = new DebuginfoSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            (int) $projectId
        );

        return $this->render('index', [
            'model'        => $searchModel,
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    public function actionDownload($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);
        if (method_exists($model, 'dumpFileAttachmentContent')) {
            $model->dumpFileAttachmentContent();
        } else {
            throw new \yii\web\ServerErrorHttpException('Download logic not migrated.');
        }
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model, 'pperm_manage_debug_info');
        if (method_exists($model, 'markForDeletion')) {
            if (!$model->markForDeletion()) {
                throw new NotFoundHttpException('The specified record doesn\'t exist in the database');
            }
        } else {
            $model->delete();
        }
        return $this->redirect(['index']);
    }

    public function actionDeleteMultiple()
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        foreach ($deleteRows as $id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_debug_info');
            if (method_exists($model, 'markForDeletion')) {
                $model->markForDeletion();
            } else {
                $model->delete();
            }
        }
        return $this->redirect(['index']);
    }

    public function actionUploadStat($period = 7, $w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->debugInfoUploadDynamics(
            (int) Yii::$app->user->getCurProjectId(),
            (int) $period
        );
    }

    public function actionUploadFile()
    {
        $this->checkAuthorization(null, 'pperm_manage_debug_info');
        $model = new DebugInfo();
        $model->project_id = (int) Yii::$app->user->getCurProjectId();
        $submitted = false;

        if ($model->load(Yii::$app->request->post())) {
            $submitted = true;
            $model->fileAttachment = UploadedFile::getInstance($model, 'fileAttachment');
            if ($model->save()) {
                $model->persistAttachment();
                Yii::$app->session->setFlash('success', "Debug symbols #{$model->id} uploaded.");
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('uploadFile', [
            'model'     => $model,
            'submitted' => $submitted,
        ]);
    }

    public function actionUploadExternal()
    {
        $action = (Yii::$app->request->post('action') === 'Check')
            ? self::ACTION_CHECK
            : self::ACTION_UPLOAD;

        $model = new DebugInfo();
        $model->load(Yii::$app->request->post());

        // If a project name was supplied (instead of an ID), resolve it.
        if (!$model->project_id) {
            $appName = Yii::$app->request->post('appname');
            if ($appName) {
                $project = \app\models\Project::findOne(['name' => $appName]);
                if ($project) {
                    $model->project_id = $project->id;
                }
            }
        }

        $alreadyExists = $model->checkFileGUIDExists();

        if ($action === self::ACTION_CHECK) {
            return $this->renderPartial('_upload', ['model' => $model, 'alreadyExists' => $alreadyExists]);
        }

        if (!$alreadyExists && $model->validate()) {
            $model->fileAttachment = UploadedFile::getInstance($model, 'fileAttachment');
            if ($model->save()) {
                $model->persistAttachment();
            }
        }

        return $this->renderPartial('_upload', ['model' => $model, 'alreadyExists' => $alreadyExists]);
    }

    protected function findModel($id)
    {
        if (($model = DebugInfo::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }

    protected function checkAuthorization($model, $permission = 'pperm_browse_debug_info')
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
