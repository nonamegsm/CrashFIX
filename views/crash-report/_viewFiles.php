<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use app\components\MiscHelpers;
use app\models\Crashreport;

$zipMembers = $model->listZipCentralDirectoryMembers();
$zipOnDisk = $model->isZipArchiveOnDisk();
$zipIndexProvider = new ArrayDataProvider([
    'allModels' => $zipMembers,
    'pagination' => false,
    'key' => 'path',
]);

$renderNameWithPreview = static function (string $filename, int $rpt): string {
    $kind = Crashreport::previewUiKind($filename);
    $out = Html::a(
        Html::encode($filename),
        ['extract-file', 'name' => $filename, 'rpt' => $rpt]
    );
    if ($kind === null) {
        return $out;
    }
    $url = $kind === 'text'
        ? Url::to(['/crash-report/preview-file-text', 'rpt' => $rpt, 'name' => $filename])
        : Url::to(['/crash-report/inline-file', 'rpt' => $rpt, 'name' => $filename]);
    $out .= ' ' . Html::button('Preview', [
        'type' => 'button',
        'class' => 'btn btn-outline-secondary btn-sm ms-1 align-baseline crash-report-file-preview-btn',
        'data' => [
            'preview-type' => $kind,
            'preview-url' => $url,
            'preview-filename' => $filename,
        ],
    ]);

    return $out;
};
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
                <p class="text-muted fst-italic mb-0">No entries could be read (empty or unreadable archive).</p>
            <?php else: ?>
                <?= GridView::widget([
                    'dataProvider' => $zipIndexProvider,
                    'summary' => false,
                    'emptyText' => 'No members.',
                    'columns' => [
                        [
                            'label' => 'Member path',
                            'format' => 'raw',
                            'value' => function ($row) use ($model, $renderNameWithPreview) {
                                if (!empty($row['is_dir'])) {
                                    return Html::encode($row['path']);
                                }

                                return $renderNameWithPreview($row['path'], (int) $model->id);
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
                'value'     => function ($data) use ($renderNameWithPreview) {
                    return $renderNameWithPreview($data->filename, (int) $data->crashreport_id);
                },
            ],
            'description',
        ],
        'emptyText' => 'No file items were extracted from this report.',
    ]) ?>

</div>

<div class="modal fade" id="crash-report-file-preview-modal" tabindex="-1" role="dialog" aria-labelledby="crash-report-file-preview-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="crash-report-file-preview-title">Preview</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="crash-report-file-preview-loading" class="d-none text-muted">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                    Loading…
                </div>
                <div id="crash-report-file-preview-error" class="alert alert-danger d-none mb-0"></div>
                <div id="crash-report-file-preview-meta" class="small text-muted mb-2 d-none"></div>
                <pre id="crash-report-file-preview-text" class="bg-light border rounded p-3 mb-0 d-none" style="max-height: 70vh; white-space: pre-wrap; word-break: break-word; font-size: 0.85rem;"></pre>
                <div id="crash-report-file-preview-image-wrap" class="text-center d-none">
                    <img id="crash-report-file-preview-image" src="" alt="" class="img-fluid rounded border" style="max-height: 75vh;" />
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = '#crash-report-file-preview-modal';
$this->registerJs(<<<JS
(function () {
    var \$m = $('{$modalId}');
    var \$load = $('#crash-report-file-preview-loading');
    var \$err = $('#crash-report-file-preview-error');
    var \$meta = $('#crash-report-file-preview-meta');
    var \$pre = $('#crash-report-file-preview-text');
    var \$imgW = $('#crash-report-file-preview-image-wrap');
    var \$img = $('#crash-report-file-preview-image');
    var \$title = $('#crash-report-file-preview-title');

    function resetModal() {
        \$load.addClass('d-none');
        \$err.addClass('d-none').text('');
        \$meta.addClass('d-none').text('');
        \$pre.addClass('d-none').text('');
        \$imgW.addClass('d-none');
        \$img.removeAttr('src').removeAttr('alt');
    }

    \$m.on('hidden.bs.modal', resetModal);

    $(document).on('click', '.crash-report-file-preview-btn', function () {
        var btn = $(this);
        var type = btn.data('preview-type');
        var url = btn.data('preview-url');
        var fname = btn.data('preview-filename') || 'File';
        resetModal();
        \$title.text('Preview: ' + fname);
        \$m.modal('show');
        \$load.removeClass('d-none');

        if (type === 'image') {
            \$load.addClass('d-none');
            \$img.off('error').on('error', function () {
                \$imgW.addClass('d-none');
                \$err.removeClass('d-none').text('Could not load image preview.');
            });
            \$img.attr('src', url).attr('alt', fname);
            \$imgW.removeClass('d-none');
            return;
        }

        if (type === 'text') {
            $.ajax({
                url: url,
                dataType: 'json',
                success: function (data) {
                    \$load.addClass('d-none');
                    if (!data || !data.ok) {
                        \$err.removeClass('d-none').text('Preview failed.');
                        return;
                    }
                    var meta = 'Size on disk: ' + (data.size != null ? data.size + ' bytes' : 'unknown');
                    if (data.truncated) {
                        meta += ' — showing first ' + data.maxBytes + ' bytes only';
                    }
                    \$meta.removeClass('d-none').text(meta);
                    \$pre.removeClass('d-none').text(data.content != null ? data.content : '');
                },
                error: function (xhr) {
                    \$load.addClass('d-none');
                    var msg = 'Could not load preview.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        msg = xhr.responseText.substring(0, 200);
                    }
                    \$err.removeClass('d-none').text(msg);
                }
            });
            return;
        }

        \$load.addClass('d-none');
        \$err.removeClass('d-none').text('Unknown preview type.');
    });
})();
JS
);
?>
