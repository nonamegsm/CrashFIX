<?php

/** @var yii\web\View $this */
/** @var app\models\MailQueue $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;

$this->title = 'Outgoing Mail - CrashFix';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Mail';

?>

<div class="mail-index">
    <div class="row mb-3" id="div_simple_search">
        <div class="col-md-12">
            <form action="<?= Url::to(['mail/index']) ?>" method="get" class="row g-3">
                <div class="col-auto">
                    <p class="mb-0">Search by subject/E-mail address:</p>
                </div>
                <div class="col-md-2">
                    <?= Html::dropDownList('status', Yii::$app->request->get('status', -1), [
                        1 => 'Pending mail',
                        2 => 'Sent mail',
                        3 => 'Failed mail',
                        -1 => 'All mail',
                    ], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= Html::textInput('q', Yii::$app->request->get('q'), ['class' => 'form-control form-control-sm', 'placeholder' => 'Subject/Email']) ?>
                </div>
                <div class="col-auto">
                    <?= Html::submitButton('Search', ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            [
                'attribute' => 'email_subject',
                'format' => 'raw',
                'value' => function ($data) {
                    return Html::a(Html::encode($data->email_subject), ['view', 'id' => $data->id]);
                },
            ],
            'recipient',
            [
                'attribute' => 'create_time',
                'format' => ['datetime', 'php:M j, Y H:i'],
            ],
            [
                'attribute' => 'sent_time',
                'format' => ['datetime', 'php:M j, Y H:i'],
            ],
            [
                'label' => 'Status',
                'value' => function ($data) {
                    return $data->getStatusStr();
                },
            ],
        ],
    ]); ?>
</div>
