<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\grid\GridView;
use app\models\Lookup;

$this->title = Yii::$app->name . ' - Manage Projects';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Projects';

?>

<div class="project-index">
    <div class="mb-3">
        <?= Html::a('Add New Project', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            [
                'attribute' => 'name',
                'format' => 'raw',
                'value' => function ($data) {
                    return Html::a(Html::encode($data->name), ['view', 'id' => $data->id]);
                },
            ],
            [
                'attribute' => 'status',
                'value' => function ($data) {
                    return Lookup::item('ProjectStatus', $data->status);
                },
            ],
            'description',
        ],
    ]); ?>
</div>
