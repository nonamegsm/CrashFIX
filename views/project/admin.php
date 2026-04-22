<?php
/** @var yii\web\View $this */
/** @var app\models\ProjectSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\helpers\Html;
use app\models\Lookup;
use app\models\Project;

$this->title = Yii::$app->name . ' - Manage Projects';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Projects';
?>

<div class="project-admin">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <?= Html::a('Add New Project', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
            <?= Html::a('Simple List',     ['index'],  ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel'  => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'id',
                'options'   => ['style' => 'width: 70px;'],
            ],
            [
                'attribute' => 'name',
                'format'    => 'raw',
                'value'     => function ($data) {
                    return Html::a(Html::encode($data->name), ['view', 'id' => $data->id]);
                },
            ],
            [
                'attribute' => 'status',
                'filter'    => [
                    Project::STATUS_ACTIVE   => Lookup::item('ProjectStatus', 1) ?: 'Active',
                    Project::STATUS_DISABLED => Lookup::item('ProjectStatus', 2) ?: 'Disabled',
                ],
                'value'     => function ($data) {
                    return Lookup::item('ProjectStatus', (int) $data->status) ?: $data->status;
                },
            ],
            'description',
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{view} {update}',
            ],
        ],
    ]) ?>
</div>
