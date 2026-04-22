<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\grid\GridView;
use yii\helpers\Html;
use app\models\Lookup;

?>
<div class="crash-report-modules">

<?= GridView::widget([
    'dataProvider' => $model->searchModules(),
    'columns' => [
        'name',
        [
            'attribute' => 'sym_load_status',
            'label'     => 'Symbol Load Status',
            'value'     => function ($data) {
                return Lookup::item('SymLoadStatus', (int) $data->sym_load_status) ?: '';
            },
        ],
        [
            'label'  => 'Loaded PDB',
            'format' => 'raw',
            'value'  => function ($data) {
                if ($data->debuginfo === null) {
                    return '<span class="text-muted">n/a</span>';
                }
                return Html::a(
                    Html::encode($data->debuginfo->filename),
                    ['debug-info/view', 'id' => $data->debuginfo->id]
                );
            },
        ],
        'file_version',
        [
            'attribute' => 'timestamp',
            'value'     => function ($data) {
                return $data->timestamp ? date('d/m/y H:i', (int) $data->timestamp) : '';
            },
        ],
    ],
]) ?>

</div>
