<?php

/** @var yii\web\View $this */
/** @var app\models\Extrafiles $model */

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\DetailView;
use app\models\Lookup;
use app\models\Extrafiles;

$this->title = Yii::$app->name . ' - Extra Files: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Administer', 'url' => ['site/admin']];
$this->params['breadcrumbs'][] = ['label' => 'Extra Files', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->name;
?>

<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'name',
        [
            'label' => 'Date from',
            'value' => $model->date_from
                ? Yii::$app->formatter->asDatetime($model->date_from, 'php:d/m/y H:i')
                : '',
        ],
        [
            'label' => 'Date to',
            'value' => $model->date_to
                ? Yii::$app->formatter->asDatetime($model->date_to, 'php:d/m/y H:i')
                : '',
        ],
        [
            'attribute' => 'status',
            'value' => Lookup::item('CrashReportStatus', (int) $model->status) ?: $model->status,
            'contentOptions' => (int) $model->status === Extrafiles::STATUS_INVALID
                ? ['class' => 'table-danger']
                : [],
        ],
        [
            'label' => 'Download',
            'format' => 'raw',
            'value' => ($model->path && is_file($model->path))
                ? Html::a(
                    Html::encode($model->name . '_' . $model->id . '.zip'),
                    ['download', 'id' => $model->id]
                )
                : '',
        ],
    ],
]) ?>

<div class="mb-3">
    <?= Html::beginForm(['delete', 'id' => $model->id], 'post', ['class' => 'd-inline']) ?>
    <?= Html::submitButton('Delete', [
        'class' => 'btn btn-danger btn-sm',
        'data-confirm' => 'Permanently delete this extra files collection?',
    ]) ?>
    <?= Html::endForm() ?>

    <?php if ((int) $model->status !== Extrafiles::STATUS_PROCESSING): ?>
        <?= Html::beginForm(['process', 'id' => $model->id], 'post', ['class' => 'd-inline ms-2']) ?>
        <?= Html::submitButton('Process', [
            'class' => 'btn btn-primary btn-sm',
            'data-confirm' => 'Extract matching attachments via the daemon and build the ZIP?',
        ]) ?>
        <?= Html::endForm() ?>
    <?php endif; ?>
</div>

<h5 class="mt-4">Files in this date range</h5>
<p class="text-muted small">Links open the attachment from the original crash report archive.</p>

<?= GridView::widget([
    'dataProvider' => $model->fileItemsDataProvider(),
    'columns' => [
        [
            'attribute' => 'filename',
            'format' => 'raw',
            'value' => static function ($row) {
                return Html::a(
                    Html::encode($row->filename),
                    ['/crash-report/extract-file', 'name' => $row->filename, 'rpt' => $row->crashreport_id]
                );
            },
        ],
        'description',
    ],
]) ?>
