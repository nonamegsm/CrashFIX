<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller as BaseController;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\UploadedFile;
use app\models\Bug;
use app\models\BugAttachment;
use app\models\BugSearch;

class BugController extends BaseController
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'Bugs';
    public $adminMenuItem;
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'download-attachment', 'status-dynamics', 'status-dist', 'create', 'delete', 'delete-multiple'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        if (Yii::$app->request->isPost) {
            $posted = Yii::$app->request->post('Bug', []);
            $model->comment        = $posted['comment'] ?? null;
            $model->fileAttachment = UploadedFile::getInstance($model, 'fileAttachment');
            if ($model->change($posted)) {
                Yii::$app->session->setFlash('success', 'Bug updated.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('view', ['model' => $model]);
    }

    public function actionCreate($crashgroup = null, $crashreport = null)
    {
        $this->checkAuthorization(null);
        $model = new Bug();

        if ($crashgroup !== null) {
            $model->crashgroups = $crashgroup;
            $model->autoFillSummary();
        }
        if ($crashreport !== null) {
            $model->crashreports = $crashreport;
            $model->autoFillSummary();
        }

        // Default to current project + version when none came pre-filled.
        if (empty($model->project_id)) {
            $model->project_id = (int) Yii::$app->user->getCurProjectId();
        }
        if ($model->appversion_id === null) {
            $model->appversion_id = (int) Yii::$app->user->getCurProjectVer();
            if ($model->appversion_id < 0) {
                $model->appversion_id = 0;
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            $model->fileAttachment = UploadedFile::getInstance($model, 'fileAttachment');
            if ($model->open()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionDelete()
    {
        if (Yii::$app->request->isPost) {
            $id = Yii::$app->request->post('id');
            if ($id === null) {
                throw new \yii\web\BadRequestHttpException('Invalid request');
            }

            $model = $this->findModel($id);
            $this->checkAuthorization($model);
            $model->delete();

            return $this->redirect(['index']);
        } else {
            throw new \yii\web\BadRequestHttpException('Invalid request. Please do not repeat this request again.');
        }
    }

    public function actionIndex($q = null, $status = null)
    {
        $this->checkAuthorization(null);

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId == false) {
            throw new \yii\web\BadRequestHttpException('Invalid request.');
        }

        $searchModel  = new BugSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            (int) $projectId,
            (int) Yii::$app->user->getCurProjectVer(),
            $q,
            $status ?? 'open'
        );

        return $this->render('index', [
            'model'        => $searchModel,
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionDownloadAttachment($id)
    {
        $bugAttachment = BugAttachment::findOne($id);
        if ($bugAttachment === null) {
            throw new ForbiddenHttpException('Invalid request');
        }

        // Determine bug logic assuming relations exist
        $bugChange = $bugAttachment->getBugChange()->one();
        $bug = $bugChange ? $bugChange->getBug()->one() : null;

        $this->checkAuthorization($bug);

        if(method_exists($bugAttachment, 'dumpFileAttachmentContent')) {
            $bugAttachment->dumpFileAttachmentContent();
        } else {
            throw new \yii\web\ServerErrorHttpException('Attachment logic not fully migrated.');
        }
    }

    public function actionStatusDynamics($period = 7, $w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->bugStatusDynamics(
            (int) Yii::$app->user->getCurProjectId(),
            (int) $period,
            (int) Yii::$app->user->getCurProjectVer()
        );
    }

    public function actionStatusDist($w = null, $h = null)
    {
        $this->checkAuthorization(null);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return Yii::$app->stats->bugStatusDistribution(
            (int) Yii::$app->user->getCurProjectId(),
            (int) Yii::$app->user->getCurProjectVer()
        );
    }

    protected function findModel($id)
    {
        if (($model = Bug::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    protected function checkAuthorization($model, $permission = 'pperm_browse_bugs')
    {
        if ($model === null) {
            $projectId = Yii::$app->user->getCurProjectId();
            if ($projectId == false) {
                return;
            }
        } else {
            $projectId = $model->project_id;
        }

        $params = ['project_id' => $projectId];
        if (!Yii::$app->user->can($permission, $params)) {
             throw new ForbiddenHttpException('You are not authorized to perform this action.');
        }
    }
}
