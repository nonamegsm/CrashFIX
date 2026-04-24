<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use app\components\MiscHelpers;

$zipMembers = $model->listZipCentralDirectoryMembers();
$zipOnDisk = $model->isZipArchiveOnDisk();
$zipIndexProvider = new ArrayDataProvider([
    'allModels' => $zipMembers,
    'pagination' => false,
    'key' => 'path',
]);
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

    <div class="card mb-3">
        <div class="card-header bg-white">
            <h6 class="mb-0">ZIP archive index</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Member names and <strong>uncompressed</strong> sizes read from the ZIP central directory on the server.
                Nothing is extracted to build this table.
            </p>
            <?php if (!$zipOnDisk): ?>
                <p class="text-muted fst-italic mb-0">The archive file is not present on disk (or storage is unavailable).</p>
            <?php elseif ($zipMembers === []): ?>
                <p class="text-muted fst-italic mb-0">No entries could be read (empty archive or unreadable file).</p>
            <?php else: ?>
                <?= GridView::widget([
                    'dataProvider' => $zipIndexProvider,
                    'summary' => false,
                    'emptyText' => 'No members.',
                    'columns' => [
                        [
                            'label' => 'Member path',
                            'format' => 'raw',
                            'value' => function ($row) use ($model) {
                                if (!empty($row['is_dir'])) {
                                    return Html::encode($row['path']);
                                }

                                return Html::a(
                                    Html::encode($row['path']),
                                    ['extract-file', 'name' => $row['path'], 'rpt' => $model->id]
                                );
                            },
                        ],
                        [
                            'label' => 'Uncompressed size',
                            'value' => function ($row) {
                                if (!empty($row['is_dir'])) {
                                    return '—';
                                }

                                return MiscHelpers::fileSizeToStr((int) $row['size']);
                            },
                        ],
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted small">Registered file items (database metadata and descriptions). You can download each file from the archive:</p>

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
