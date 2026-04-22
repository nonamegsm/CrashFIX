<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */
/** @var yii\data\ActiveDataProvider|null $stackTrace */
/** @var app\models\Thread|null $threadModel */

use yii\grid\GridView;
use yii\helpers\Html;

?>
<div class="crash-report-threads">

<?php if ($stackTrace === null): ?>

    <?= GridView::widget([
        'dataProvider' => $model->searchThreads(),
        'columns' => [
            [
                'label' => 'Thread Id',
                'value' => function ($data) {
                    return '0x' . dechex((int) $data->thread_id);
                },
            ],
            [
                'label' => 'Exception',
                'value' => function ($data) use ($model) {
                    return ((int) $data->thread_id === (int) $model->exception_thread_id) ? 'Yes' : '';
                },
            ],
            [
                'label' => 'Thread Procedure',
                'value' => function ($data) {
                    return $data->getThreadFuncName();
                },
            ],
            [
                'label'  => 'Stack Trace',
                'format' => 'raw',
                'value'  => function ($data) use ($model) {
                    return Html::a(
                        'View stack trace',
                        ['view', 'id' => $model->id, 'tab' => 'Threads', 'thread' => $data->id]
                    );
                },
            ],
        ],
    ]) ?>

<?php else: ?>

    <div class="text-muted mb-2">
        Viewing stack trace for thread <code>0x<?= dechex((int) $threadModel->thread_id) ?></code>
        &mdash;
        <?= Html::a('Back to thread list', ['view', 'id' => $model->id, 'tab' => 'Threads']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $stackTrace,
        'summary'      => '',
        'columns'      => [
            [
                'label'         => 'Frame',
                'value'         => function ($data) {
                    return $data->getTitle();
                },
                'contentOptions' => ['style' => 'font-family: monospace; white-space: nowrap;'],
            ],
        ],
    ]) ?>

<?php endif; ?>

</div>
