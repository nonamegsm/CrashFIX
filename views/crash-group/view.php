<?php

/** @var yii\web\View $this */
/** @var app\models\Crashgroup $model */
/** @var app\models\Crashreport $crashReportModel */
/** @var yii\data\ActiveDataProvider $crashReportDataProvider */

use yii\widgets\DetailView;
use yii\helpers\Html;
use app\components\MiscHelpers;
use app\models\Bug;

$this->title = 'View Collection #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Crash Collections', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Collection #' . $model->id;

$user = Yii::$app->user;
$curProjectId = $user->getCurProjectId();

?>

<div class="crash-group-view">
    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?php if ($user->can('pperm_manage_bugs', ['project_id' => $curProjectId])): ?>
                <?= Html::a('Open Bug', ['bug/create', 'crashgroup' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
            <?php endif; ?>

            <?php if ($user->can('pperm_manage_crash_reports', ['project_id' => $curProjectId])): ?>
                <?= Html::a('Delete Collection', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-outline-danger',
                    'data' => [
                        'confirm' => 'Are you sure you want to permanently delete the collection #' . $model->id . '?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'title',
            'created:datetime',
            'crashReportCount',
            [
                'label' => 'Open Bugs',
                'format' => 'raw',
                'value' => function ($model) {
                    $bugList = '';
                    foreach ($model->bugs as $bugCrashGroup) {
                        if ($bugCrashGroup->bug->status < Bug::STATUS_OPEN_MAX) {
                            $bugList .= Html::a('#' . $bugCrashGroup->bug_id, ['bug/view', 'id' => $bugCrashGroup->bug_id]) . ' ';
                        }
                    }
                    return $bugList ?: 'None';
                },
            ],
        ],
    ]) ?>

    <div class="mt-4">
        <h5>Crash Reports Belonging to this Collection:</h5>
        <?= $this->render('/crash-report/_reportList', [
            'model' => $crashReportModel,
            'dataProvider' => $crashReportDataProvider,
        ]) ?>
    </div>

    <div class="mt-3 text-muted small">
        <?php
        $totalFileSize = 0;
        $percentOfQuota = 0;
        $count = $model->getCrashReportCount($totalFileSize, $percentOfQuota);
        $totalFileSizeStr = MiscHelpers::fileSizeToStr($totalFileSize);
        echo "This collection contains total $totalFileSizeStr in $count file(s)";
        if ($percentOfQuota >= 0) echo " (" . sprintf("%.0f", $percentOfQuota) . "% of max count).";
        ?>
    </div>
</div>
