<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;

$this->title = 'Daemon Status - CrashFix';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Daemon';
?>

<div class="daemon-view">
    <div class="subheader">Daemon Status:</div>
    <div id="daemon_status" class="loading border p-3 rounded mb-4">
        <i>Querying daemon status ...</i>
    </div>

    <div class="subheader">Recent Operations:</div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider ?? new \yii\data\ArrayDataProvider([]),
        'columns' => [
            'id',
            'timestamp:datetime',
            'operation_type',
            'status',
        ],
    ]) ?>
</div>

<?php 
$statusUrl = \yii\helpers\Url::to(['site/daemon-status']);
$this->registerJs(<<<JS
    $.ajax({		
        url: "$statusUrl",
        type: 'GET'
    }).done(function( msg ) {
        $("#daemon_status").replaceWith(msg);
    });
JS
);
?>
