<?php

/** @var yii\web\View $this */
/** @var app\models\Debuginfo $model */

use yii\widgets\DetailView;
use yii\helpers\Html;
use app\components\MiscHelpers;

$this->title = 'View Debug Info File #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Debug Info', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Debug Info File #' . $model->id;

?>

<div class="debug-info-view">
    <?php if (!empty($model->processingErrors)): ?>
        <div class="alert alert-danger">
            There were some processing errors:
            <ul>
                <?php foreach ($model->processingErrors as $error): ?>
                    <li><?= Html::encode($error->message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?= Html::a('Download File', ['download', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
            <?= Html::a('Delete File', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-outline-danger',
                'data' => [
                    'confirm' => 'Are you sure you want to permanently delete this debug info file?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            [
                'attribute' => 'dateuploaded',
                'value' => date("d/m/y H:i", $model->dateuploaded),
            ],
            'filename',
            [
                'attribute' => 'status',
                'value' => $model->getStatusStr(),
            ],
            [
                'attribute' => 'filesize',
                'value' => MiscHelpers::fileSizeToStr($model->filesize),
            ],
            [
                'attribute' => 'guid',
                'value' => substr($model->guid, 0, 4) != "tmp_" ? $model->guid : "n/a",
            ],
            'md5',
        ],
    ]) ?>
</div>
