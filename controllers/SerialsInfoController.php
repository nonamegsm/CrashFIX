<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;
use app\models\SerialsinfoSearch;

/**
 * Read-only admin view over the `view_serials_report_count` SQL view.
 *
 * Mirror of the Yii1 SerialsInfoController on the legacy
 * php8-compat branch. Two routes (index, serial-report) are
 * exposed for path compatibility - both render the same page since
 * the original Yii1 actions were duplicates of each other.
 */
class SerialsInfoController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'SerialsInfo';
    public $adminMenuItem;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        // Same permission gate the Yii1 controller used:
                        // anyone who can browse some crash reports can
                        // see the serials view (it's derived from
                        // crash-report custom-prop data).
                        'actions' => ['index', 'serial-report'],
                        'allow'   => true,
                        'roles'   => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $this->checkAuthorization();

        $searchModel  = new SerialsinfoSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Path-compat alias of actionIndex. The Yii1 controller had a
     * separate actionSerialReport that rendered an identical view;
     * keeping the route as a thin redirect avoids breaking any
     * external links / bookmarks pointing at /serials-info/serial-report.
     */
    public function actionSerialReport()
    {
        return $this->redirect(['index']);
    }

    /**
     * Project-scoped authorisation guard. Same shape as the other
     * controllers in this app: refuses when no current project is
     * selected or when the user lacks browse-crash-reports permission
     * on the current project.
     */
    protected function checkAuthorization(): void
    {
        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId === false || $projectId === null) {
            throw new ForbiddenHttpException('Select a project before browsing serials info.');
        }
        if (!Yii::$app->user->can('pperm_browse_some_crash_reports',
                                   ['project_id' => $projectId])) {
            throw new ForbiddenHttpException('You are not authorized to perform this action.');
        }
    }
}
