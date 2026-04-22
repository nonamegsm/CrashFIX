<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use app\models\Project;
use app\models\ProjectSearch;
use app\models\User;
use app\models\UserProjectAccess;
use app\models\Usergroup;

class ProjectController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'Administer';
    public $adminMenuItem = 'Projects';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'admin', 'view', 'create', 'update', 'add-user', 'delete-user', 'disable', 'enable', 'delete'],
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
                    'delete-user' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        
        // searchUsers assumed to be on the Project model
        $dataProvider = method_exists($model, 'searchUsers') ? $model->searchUsers() : new ActiveDataProvider([
            'query' => UserProjectAccess::find()->where(['project_id' => $id])
        ]);

        return $this->render('view', [
            'model' => $model,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $model = new Project();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // Automatically assign the current user to this project as Admin
            $access = new UserProjectAccess();
            $access->user_id = Yii::$app->user->id;
            $access->project_id = $model->id;
            
            // Find Admin group ID, default to 1
            $adminGroup = Usergroup::findOne(['name' => 'Admin']);
            $access->usergroup_id = $adminGroup ? $adminGroup->id : 1;
            
            if ($access->save()) {
                Yii::$app->session->setFlash('success', 'Project ' . $model->name . ' has been created and you have been assigned to it.');
            } else {
                Yii::$app->session->setFlash('success', 'Project ' . $model->name . ' has been created, but automatic assignment failed.');
            }
            
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
            Yii::$app->session->setFlash('success', 'Project information has been updated.');
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
                $model->status = 0; // STATUS_DISABLED assumed 0
                $model->save(false);
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
                $model->status = 1; // STATUS_ACTIVE assumed 1
                $model->save(false);
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
                Yii::$app->session->setFlash('success', 'The project ' . $model->name . ' has been deleted.');
            } else {
                Yii::$app->session->setFlash('error', 'The project ' . $model->name . ' could not be deleted.');
            }
            return $this->redirect(['index']);
        }
        throw new NotFoundHttpException('Invalid request');
    }

    public function actionAddUser()
    {
        $projectId = Yii::$app->request->post('id') ?: Yii::$app->request->get('id');
        if (!$projectId) throw new ForbiddenHttpException('Invalid request');

        $project = $this->findModel($projectId);

        if (Yii::$app->request->isPost && Yii::$app->request->post('User')) {
            $items = Yii::$app->request->post('User');
            $checks = Yii::$app->request->post('check', []);

            foreach ($items as $userId => $item) {
                $model = UserProjectAccess::findOne(['user_id' => $userId, 'project_id' => $projectId]);

                if (in_array($userId, $checks)) {
                    if ($model === null) {
                        $model = new UserProjectAccess();
                        $model->user_id = $userId;
                        $model->project_id = $projectId;
                        $model->usergroup_id = $item['usergroup'];
                        if (!$model->save()) {
                            throw new \yii\web\ServerErrorHttpException('Error saving record to database');
                        }
                    } else {
                         $model->usergroup_id = $item['usergroup'];
                         $model->save();
                    }
                } else {
                    if ($model !== null) {
                        $model->delete();
                    }
                }
            }

            Yii::$app->session->setFlash('success', 'User roles have been updated.');
            return $this->redirect(['view', 'id' => $projectId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => User::find()->orderBy('username ASC'),
            'pagination' => false,
        ]);

        return $this->render('addUser', [
            'project' => $project,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionDeleteUser()
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        $projectId = Yii::$app->request->post('project_id');

        foreach ($deleteRows as $id) {
            $model = UserProjectAccess::findOne($id);
            if ($model) {
                $model->delete();
            }
        }

        Yii::$app->session->setFlash('success', 'User roles have been updated.');
        return $this->redirect(['view', 'id' => $projectId]);
    }

    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Project::find(),
        ]);
        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Filterable / sortable admin grid of all projects.
     * The legacy app exposed this as a separate action; the basic
     * actionIndex above is kept for compatibility with existing menu links.
     */
    public function actionAdmin()
    {
        $searchModel  = new ProjectSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('admin', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    protected function findModel($id)
    {
        if (($model = Project::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
