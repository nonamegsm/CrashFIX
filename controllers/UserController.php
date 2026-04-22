<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use app\models\User;
use app\models\UserProjectAccess;
use app\models\Project;

class UserController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'Administer';
    public $adminMenuItem = 'Users';

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['view'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['index', 'create', 'update', 'retire', 'resurrect', 'delete', 'add-project-role', 'delete-project-roles'],
                        'allow' => true,
                        'roles' => ['gperm_access_admin_panel'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'retire' => ['POST'],
                    'resurrect' => ['POST'],
                    'delete-project-roles' => ['POST'],
                ],
            ],
        ];
    }

    public function actionView($id)
    {
        if (!Yii::$app->user->can('gperm_access_admin_panel') &&
            !Yii::$app->user->can('gperm_view_own_profile', ['user_id' => $id])) {
            throw new ForbiddenHttpException('You are not authorized to perform this action.');
        }

        $model = $this->findModel($id);
        $userProjectAccess = new ActiveDataProvider([
            'query' => UserProjectAccess::find()->where(['user_id' => $id])->with(['project', 'usergroup']),
            'pagination' => false,
        ]);

        if (!Yii::$app->user->can('gperm_access_admin_panel')) {
            $this->adminMenuItem = null;
        }

        return $this->render('view', [
            'model' => $model,
            'userProjectAccess' => $userProjectAccess,
        ]);
    }

    public function actionCreate()
    {
        $model = new User();
        $model->scenario = 'create';
        $model->usergroup = 4;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if ($model->sendEmailWithAccountActivationLink()) {
                Yii::$app->session->setFlash('success', 'The new user has been added successfully. An E-mail message with account activation link has been sent to new user\'s mailbox.');
            } else {
                Yii::$app->session->setFlash('error', 'The new user has been added. However an E-mail message with account activation link couldn\'t be sent to new user\'s mailbox. Please check your SMTP server.');
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
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    public function actionRetire()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            if (method_exists($model, 'retire')) {
                $model->retire();
            } else {
                $model->status = 2; // User::STATUS_DISABLED
                $model->save(false);
            }
            return $this->redirect(['index']);
        }
        throw new NotFoundHttpException('Invalid request.');
    }

    public function actionResurrect()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $model = $this->findModel($id);
            if ($model->status != 2) { // 2 = User::STATUS_DISABLED
                throw new ForbiddenHttpException('Invalid request.');
            }
            if (method_exists($model, 'resurrect')) {
                $model->resurrect();
            } else {
                $model->status = 1; // User::STATUS_ACTIVE
                $model->save(false);
            }
            return $this->redirect(['index']);
        }
        throw new NotFoundHttpException('Invalid request.');
    }

    public function actionDelete()
    {
        $id = Yii::$app->request->post('id');
        if ($id) {
            $this->findModel($id)->delete();
            return $this->redirect(['index']);
        }
        throw new NotFoundHttpException('Invalid request.');
    }

    public function actionIndex()
    {
        $status = Yii::$app->request->get('status', 1);
        $username = Yii::$app->request->get('username');

        $query = User::find()->with('group');

        if ($status != -1) {
            $query->andWhere(['status' => $status]);
        }

        if (!empty($username)) {
            $query->andWhere(['like', 'username', $username])
                  ->orWhere(['like', 'email', $username]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['username' => SORT_ASC],
            ]
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionAddProjectRole()
    {
        $userId = Yii::$app->request->post('id');
        if (!$userId) $userId = Yii::$app->request->get('id');
        if (!$userId) throw new ForbiddenHttpException('Invalid request');

        $user = $this->findModel($userId);

        if (Yii::$app->request->isPost && Yii::$app->request->post('Project')) {
            $items = Yii::$app->request->post('Project');
            $checks = Yii::$app->request->post('check', []);

            foreach ($items as $projectId => $item) {
                $model = UserProjectAccess::findOne(['user_id' => $userId, 'project_id' => $projectId]);

                if (in_array($projectId, $checks)) {
                    if ($model === null) {
                        $model = new UserProjectAccess();
                        $model->user_id = $userId;
                        $model->project_id = $projectId;
                        $model->usergroup_id = $item['role'];
                        if (!$model->save()) {
                            throw new \yii\web\ServerErrorHttpException('Error saving record to database');
                        }
                    } else {
                        $model->usergroup_id = $item['role'];
                        $model->save();
                    }
                } else {
                    if ($model !== null) {
                        $model->delete();
                    }
                }
            }

            Yii::$app->session->setFlash('success', 'User roles have been updated.');
            return $this->redirect(['view', 'id' => $userId]);
        }

        $projects = Project::find()->orderBy('name ASC')->all();

        return $this->render('addProjectRole', [
            'user' => $user,
            'projects' => $projects,
        ]);
    }

    public function actionDeleteProjectRoles()
    {
        $deleteRows = Yii::$app->request->post('DeleteRows', []);
        $userId = Yii::$app->request->post('user_id');

        foreach ($deleteRows as $id) {
            $model = UserProjectAccess::findOne($id);
            if ($model) {
                $model->delete();
            }
        }

        Yii::$app->session->setFlash('success', 'User roles have been updated.');
        return $this->redirect(['view', 'id' => $userId]);
    }

    protected function findModel($id)
    {
        if (($model = User::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
