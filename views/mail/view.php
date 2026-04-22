<?php

/** @var yii\web\View $this */
/** @var app\models\MailQueue $model */

use yii\widgets\DetailView;
use yii\helpers\Html;
use app\models\MailQueue;

$this->title = 'View Mail #' . $model->id;
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Mail', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="mail-view">
    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?php if ($model->status != MailQueue::STATUS_PENDING): ?>
                <?= Html::a('Resend Mail', ['reset-status', 'id' => $model->id], [
                    'class' => 'btn btn-outline-primary',
                    'data' => [
                        'confirm' => 'Are you sure you want to reset status of mail #' . $model->id . '?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>

            <?= Html::a('Delete Mail', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-outline-danger',
                'data' => [
                    'confirm' => 'Are you sure you want to permanently delete mail #' . $model->id . '?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'recipient',
            'email_subject',
            [
                'attribute' => 'status',
                'value' => $model->getStatusStr(),
            ],
        ],
    ]) ?>

    <div class="mt-3">
        <h6>Email Body:</h6>
        <div class="p-3 bg-light border rounded">
            <pre><?= Html::encode($model->email_body) ?></pre>
        </div>
    </div>
</div>
