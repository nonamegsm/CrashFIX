<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\grid\GridView;

?>
<div class="crash-report-customprops">

<?= GridView::widget([
    'dataProvider' => $model->searchCustomProps(),
    'columns' => [
        [
            'attribute'      => 'name',
            'contentOptions' => ['style' => 'font-weight: 600; width: 30%;'],
        ],
        [
            'attribute'      => 'value',
            'format'         => 'ntext',
            'contentOptions' => ['style' => 'font-family: monospace;'],
        ],
    ],
    'emptyText' => 'No custom properties were submitted with this report.',
]) ?>

</div>
