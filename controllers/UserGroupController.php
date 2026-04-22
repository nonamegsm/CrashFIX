<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use app\models\Usergroup;
use app\models\UsergroupSearch;

class UserGroupController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'Administer';
    public $adminMenuItem = 'Groups';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'create', 'update', 'disable', 'enable', 'delete'],
                        'allow' => true,
                        'roles' => ['gperm_access_admin_panel'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'disable' => ['POST'],
                    'enable' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        
        // searchUsers assumed to be on the model
        $dataProvider = method_exists($model, 'searchUsers') ? $model->searchUsers() : new ActiveDataProvider([
            'query' => \app\models\User::find()->where(['usergroup' => $id])
        ]);

        return $this->render('view', [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new Usergroup();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionDisable()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            if (method_exists($model, 'enable')) {
                $model->enable(false);
            } else {
                // status logic assumed
            }
            return $this->redirect(['view', 'id' => $id]);
        }
        throw new NotFoundHttpException('Invalid request');
    }

    public function actionEnable()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            if (method_exists($model, 'enable')) {
                $model->enable(true);
            } else {
                // status logic assumed
            }
            return $this->redirect(['view', 'id' => $id]);
        }
        throw new NotFoundHttpException('Invalid request');
    }

    public function actionDelete()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            if ($model->delete()) {
                Yii::$app->session->setFlash('success', 'The group ' . $model->name . ' has been deleted.');
            } else {
                Yii::$app->session->setFlash('error', 'The group ' . $model->name . ' could not be deleted, because some users are still belonging to it.');
            }
            return $this->redirect(['index']);
        }
        throw new NotFoundHttpException('Invalid request');
    }

    public function actionIndex()
    {
        $searchModel  = new UsergroupSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    protected function findModel($id)
    {
        if (($model = Usergroup::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
