<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\CrashGroup;
use app\models\CrashgroupSearch;
use app\models\CrashReport;
use app\models\CrashreportSearch;

class CrashGroupController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'CrashGroups';
    public $adminMenuItem;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'delete', 'delete-multiple'],
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

    public function actionIndex($q = null, $status = 'open')
    {
        $this->checkAuthorization(null);

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId == false) throw new \yii\web\BadRequestHttpException('Invalid request.');

        $searchModel  = new CrashgroupSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            (int) $projectId,
            (int) Yii::$app->user->getCurProjectVer(),
            $q,
            $status
        );

        return $this->render('index', [
            'model'        => $searchModel,
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id, $q = null)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        // List crash reports belonging to this group, scoped to the same project.
        $reportSearch  = new CrashreportSearch();
        $params = Yii::$app->request->queryParams;
        // Force the groupid filter regardless of GET input.
        $params['CrashreportSearch']['groupid'] = $model->id;

        $reportProvider = $reportSearch->search(
            $params,
            (int) $model->project_id,
            -1
        );

        return $this->render('view', [
            'model'                   => $model,
            'crashReportModel'        => $reportSearch,
            'crashReportDataProvider' => $reportProvider,
        ]);
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
        throw new NotFoundHttpException('Invalid request');
    }

    public function actionDeleteMultiple()
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        foreach ($deleteRows as $id) {
            $model = $this->findModel($id);
            $this->checkAuthorization($model, 'pperm_manage_crash_reports');
            $model->delete();
        }
        return $this->redirect(['index']);
    }

    protected function findModel($id)
    {
        if (($model = CrashGroup::findOne($id)) !== null) {
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
