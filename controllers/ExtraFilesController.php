<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\BadRequestHttpException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use app\models\Extrafiles;
use app\models\ExtrafilesSearch;
use app\models\Crashreport;
use app\models\Project;

/**
 * Port of the Yii1 ExtraFilesController: build ZIP archives of
 * non-standard crash-report attachments over a received-date range.
 */
class ExtraFilesController extends Controller
{
    public $layout = 'column2';
    public $sidebarActiveItem = 'ExtraFiles';
    public $adminMenuItem;

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'create', 'delete', 'process', 'download'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'process' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $this->checkAuthorization(null);

        $searchModel = new ExtrafilesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        return $this->render('view', ['model' => $model]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        if ($model->path !== null && $model->path !== '' && is_file($model->path)) {
            @unlink($model->path);
        }
        $model->delete();

        return $this->redirect(['index']);
    }

    public function actionProcess($id)
    {
        $extra = $this->findModel($id);
        $this->checkAuthorization($extra);

        if ((int) $extra->status === Extrafiles::STATUS_PROCESSING) {
            Yii::$app->session->setFlash('warning', 'This collection is already being processed.');
            return $this->redirect(['view', 'id' => $extra->id]);
        }

        $extra->status = Extrafiles::STATUS_PROCESSING;
        $extra->save(false, ['status']);

        $files = $extra->fileItemsQuery()->all();
        if ($files === []) {
            $extra->status = Extrafiles::STATUS_PROCESSED;
            $extra->save(false, ['status']);
            return $this->redirect(['view', 'id' => $extra->id]);
        }

        $storage = Yii::$app->storage;
        $workDir = $storage->extraFilesCollectionWorkDir($extra->name, (int) $extra->id);
        $zipPath = $storage->extraFilesCollectionZipPath($extra->name, (int) $extra->id);

        $storage->mkdirRecursive($workDir);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $extra->status = Extrafiles::STATUS_INVALID;
            $extra->save(false, ['status']);
            Yii::$app->session->setFlash('error', 'Could not create ZIP archive.');
            return $this->redirect(['view', 'id' => $extra->id]);
        }

        $warnings = [];
        foreach ($files as $file) {
            $crashReport = Crashreport::findOne((int) $file->crashreport_id);
            if ($crashReport === null) {
                continue;
            }
            $crashReportFileName = Yii::$app->storage->crashReportPath(
                (int) $crashReport->project_id,
                (int) $crashReport->id
            );
            $fileName = $file->crashreport_id . '___' . $file->filename;
            $outFile = $workDir . DIRECTORY_SEPARATOR . basename(str_replace(['/', '\\'], '_', $fileName));

            $command = 'dumper --extract-file "'
                . str_replace('"', '\\"', $crashReportFileName) . '" "'
                . str_replace('"', '\\"', $file->filename) . '" "'
                . str_replace('"', '\\"', $outFile) . '"';

            $response = '';
            $retCode = Yii::$app->daemon->execCommand($command, $response);
            if ($retCode !== 0) {
                $warnings[] = 'Daemon error for ' . $file->filename . ': ' . $response;
            } elseif (is_file($outFile)) {
                $zip->addFile($outFile, $fileName);
            }
        }

        $zipOk = $zip->close();
        $storage->rmdirRecursive($workDir);

        if (!$zipOk) {
            $extra->status = Extrafiles::STATUS_INVALID;
            $extra->save(false, ['status']);
            @unlink($zipPath);
            Yii::$app->session->setFlash('error', 'Could not finalize ZIP archive.');
            return $this->redirect(['view', 'id' => $extra->id]);
        }

        $extra->path = $zipPath;
        $extra->status = Extrafiles::STATUS_PROCESSED;
        $extra->save(false, ['path', 'status']);

        if ($warnings !== []) {
            Yii::$app->session->setFlash('warning', implode("\n", array_slice($warnings, 0, 5))
                . (count($warnings) > 5 ? "\n…" : ''));
        }

        return $this->redirect(['view', 'id' => $extra->id]);
    }

    public function actionCreate($date_from, $date_to)
    {
        $this->checkAuthorization(null);

        $dateFrom = $this->parseLegacyDate(trim((string) $date_from));
        $dateTo = $this->parseLegacyDate(trim((string) $date_to));

        if ($dateTo === 0 || $dateFrom > $dateTo) {
            throw new BadRequestHttpException('Invalid date range selected.');
        }

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId === false || $projectId === null) {
            throw new ForbiddenHttpException('Select a project first.');
        }

        $project = Project::findOne((int) $projectId);
        if ($project === null) {
            throw new NotFoundHttpException('Project not found.');
        }

        $extra = new Extrafiles();
        $extra->project_id = (int) $project->id;
        $extra->name = $project->name . '_' . date('Ymd', $dateFrom) . '_' . date('Ymd', $dateTo);
        $extra->date_from = $dateFrom;
        $extra->date_to = $dateTo;
        $extra->status = Extrafiles::STATUS_WAITING;

        if (!$extra->save()) {
            Yii::$app->session->setFlash('error', 'Could not save collection: ' . json_encode($extra->errors));
            return $this->redirect(['index']);
        }

        return $this->redirect(['view', 'id' => $extra->id]);
    }

    public function actionDownload($id)
    {
        $model = $this->findModel($id);
        $this->checkAuthorization($model);

        if ($model->path === null || $model->path === '' || !is_file($model->path)) {
            throw new NotFoundHttpException('ZIP file is not available yet. Run Process first.');
        }

        Yii::$app->storage->streamDownload(
            $model->path,
            $model->name . '_' . $model->id . '.zip',
            true
        );
    }

    protected function findModel($id): Extrafiles
    {
        $model = Extrafiles::findOne((int) $id);
        if ($model === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
        return $model;
    }

    /**
     * @param Extrafiles|null $model
     */
    protected function checkAuthorization(?Extrafiles $model): void
    {
        if ($model === null) {
            $projectId = Yii::$app->user->getCurProjectId();
            if ($projectId === false || $projectId === null) {
                throw new ForbiddenHttpException('Select a project before using Extra Files.');
            }
        } else {
            $projectId = $model->project_id;
        }

        if (!Yii::$app->user->can('pperm_browse_crash_reports', ['project_id' => $projectId])) {
            throw new ForbiddenHttpException('You are not authorized to perform this action.');
        }
    }

    private function parseLegacyDate(string $raw): int
    {
        if ($raw === '') {
            return 0;
        }
        $dtime = false;
        if (strlen($raw) === 10) {
            $dtime = \DateTime::createFromFormat('d/m/Y', $raw);
        } elseif (strlen($raw) === 8) {
            $dtime = \DateTime::createFromFormat('d/m/y', $raw);
        }
        return $dtime ? $dtime->getTimestamp() : 0;
    }
}
