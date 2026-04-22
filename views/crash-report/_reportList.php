<?php

/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\helpers\Html;
use app\components\MiscHelpers;

?>

<div class="grid-view">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\CheckboxColumn', 'name' => 'DeleteRows'],
            [
                'attribute' => 'id',
                'format' => 'raw',
                'value' => function ($data) {
                    return Html::a($data->id, ['view', 'id' => $data->id]);
                },
            ],
            [
                'attribute' => 'date_created',
                'value' => function ($data) {
                    return date("d/m/y H:i", $data->date_created);
                },
            ],
            [
                'label' => 'Version',
                'value' => function ($data) {
                    return $data->appversion ? $data->appversion->version : '';
                },
            ],
            'ip_address',
            [
                'attribute' => 'filesize',
                'value' => function ($data) {
                    return MiscHelpers::fileSizeToStr($data->filesize);
                },
            ],
            [
                'label' => 'Exception',
                'value' => 'exception_type',
            ],
            [
                'label' => 'Address',
                'value' => 'exception_address',
            ],
        ],
    ]); ?>
</div>
