<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\grid\GridView;
use yii\helpers\Html;
use app\components\MiscHelpers;

?>
<div class="crash-report-files">

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">File Name</dt>
                <dd class="col-sm-9"><?= Html::encode($model->srcfilename) ?></dd>

                <dt class="col-sm-3">File Size</dt>
                <dd class="col-sm-9"><?= Html::encode(MiscHelpers::fileSizeToStr((int) $model->filesize)) ?></dd>

                <dt class="col-sm-3">MD5 Hash</dt>
                <dd class="col-sm-9"><code><?= Html::encode($model->md5) ?></code></dd>
            </dl>
            <div class="mt-3">
                <?= Html::a(
                    'Download Entire ZIP Archive',
                    ['download', 'id' => $model->id],
                    ['class' => 'btn btn-primary btn-sm']
                ) ?>
            </div>
        </div>
    </div>

    <p class="text-muted small">You can also download individual files contained in this crash report archive:</p>

    <?= GridView::widget([
        'dataProvider' => $model->searchFileItems(),
        'columns' => [
            [
                'attribute' => 'filename',
                'format'    => 'raw',
                'value'     => function ($data) {
                    return Html::a(
                        Html::encode($data->filename),
                        ['extract-file', 'name' => $data->filename, 'rpt' => $data->crashreport_id]
                    );
                },
            ],
            'description',
        ],
        'emptyText' => 'No file items were extracted from this report.',
    ]) ?>

</div>
