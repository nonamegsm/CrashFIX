<?php


class SerialsInfoController extends Controller
{
    // Use two-column layout
    public $layout = '//layouts/column2';
    public $sidebarActiveItem = 'DebugInfo';

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow',  // Allow not authenticated users
                'actions' => array('uploadExternal'),
                'users' => array('?'),
            ),
            array('allow',  // Allow authenticated users
                'actions' => array(
                    'index',
                    'view',
                    'serialReport'
                ),
                'users' => array('@'),
            ),
            array('deny',  // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * Declares class-based actions.
     */
    public function actions()
    {
        return array();
    }

    public function actionIndex()
    {
        // Check if user is authorized to perform this action
        $this->checkAuthorization(null);

        // Create new model that will contain search options.
        $model = new SerialsInfo('search');
        $model->unsetAttributes();  // clear any default values

        if (isset($_GET['SerialsInfo'])) {
            $model->attributes = $_GET['SerialsInfo'];
        }

        // Render view
        $this->render('index', array(
            'model' => $model,
        ));
    }

    public function actionSerialReport()
    {
        // Check if user is authorized to perform this action
        $this->checkAuthorization(null);

        // Create new model that will contain search options.
        $model = new SerialsInfo('search');
        $model->unsetAttributes();  // clear any default values

        if (isset($_GET['SerialsInfo'])) {
            $model->attributes = $_GET['SerialsInfo'];
        }

        // Render view
        $this->render('serialReport', array(
            'model' => $model,
        ));
    }

    /**
     * Checks if user is authorized to perform the action.
     * @param SerialsInfo $model Authorization object. Can be null.
     * @param string $permission Permission name.
     * @return void
     * @throws CHttpException
     */
    protected function checkAuthorization($model, $permission = 'pperm_browse_some_crash_reports')
    {
        if ($model == null) {
            $projectId = Yii::app()->user->getCurProjectId();
            if ($projectId == false) {
                throw new CHttpException(403, 'You are not authorized to perform this action.');
            }
        } else {
            $projectId = $model->project_id;
        }

        // Check if user can perform this action
        if (!Yii::app()->user->checkAccess($permission, array('project_id' => $projectId))) {
            throw new CHttpException(403, 'You are not authorized to perform this action.');
        }
    }
}
