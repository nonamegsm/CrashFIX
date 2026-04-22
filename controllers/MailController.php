<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use app\models\MailQueue;

class MailController extends Controller 
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'Administer';
    public $adminMenuItem = 'Mail';

    public function behaviors() 
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'delete', 'reset-status'],
                        'allow' => true,
                        'roles' => ['gperm_access_admin_panel'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'reset-status' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView($id) 
    {   
        $model = $this->findModel($id);
        return $this->render('view', ['model' => $model]);
    }

    public function actionIndex() 
    {
        $status = Yii::$app->request->get('status', -1);
        $q = Yii::$app->request->get('q');

        $query = MailQueue::find();

        if ($status != -1) {
            $query->andWhere(['status' => $status]);
        }

        if (!empty($q)) {
            $query->andWhere(['like', 'email_subject', $q])
                  ->orWhere(['like', 'recipient', $q]);
        }

        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['id' => SORT_DESC],
            ]
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionDelete() 
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            $model->delete();
            Yii::$app->session->setFlash('success', 'Mail message #' . $model->id . ' has been deleted.');
            return $this->redirect(['index']);
        }
        throw new \yii\web\BadRequestHttpException('Invalid request.');
    }

    public function actionResetStatus() 
    {   
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            // Assuming STATUS_PENDING is 0 based on typical queue logic
            $model->status = 0; 
            $model->save();
            Yii::$app->session->setFlash('success', 'Status of mail message #' . $model->id . ' has been reset.');
            return $this->render('view', ['model' => $model]);
        }
        throw new \yii\web\BadRequestHttpException('Invalid request.');
    }

    protected function findModel($id) 
    {
        if (($model = MailQueue::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
