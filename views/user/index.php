<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;

$this->title = 'Users - CrashFix';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Users';

?>

<div class="user-index">
    <!-- Simple Search Form -->
    <div class="row mb-3" id="div_simple_search">
        <div class="col-md-12">
            <form action="<?= Url::to(['user/index']) ?>" method="get" class="row g-3">
                <div class="col-auto">
                    <p class="mb-0">Search by user name/E-mail address:</p>
                </div>
                <div class="col-md-2">
                    <?= Html::dropDownList('status', Yii::$app->request->get('status', 1), [
                        1 => 'Active users',
                        2 => 'Disabled users',
                        -1 => 'All users',
                    ], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= Html::textInput('username', Yii::$app->request->get('username'), ['class' => 'form-control form-control-sm', 'placeholder' => 'Username/Email']) ?>
                </div>
                <div class="col-auto">
                    <?= Html::submitButton('Search', ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <?= Html::a('Add New User', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            [
                'attribute' => 'username',
                'format' => 'raw',
                'value' => function ($data) {
                    return Html::a(Html::encode($data->username), ['view', 'id' => $data->id]);
                },
            ],
            [
                'label' => 'Group',
                'attribute' => 'usergroup',
                'format' => 'raw',
                'value' => function ($data) {
                    return $data->group ? Html::a(Html::encode($data->group->name), ['user-group/view', 'id' => $data->usergroup]) : '';
                },
            ],
            [
                'label' => 'Status',
                'value' => function ($data) {
                    return $data->getEffectiveStatusStr();
                },
            ],
            'email:email',
        ],
    ]); ?>
</div>
