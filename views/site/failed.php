<?php

/** @var yii\web\View $this */
/** @var \yii\data\ActiveDataProvider|null $crashProvider */
/** @var \yii\data\ActiveDataProvider|null $debugProvider */
/** @var int $projectId */
/** @var bool $canCrash */
/** @var bool $canDebug */
/** @var string $crashQ */
/** @var string $debugQ */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\components\MiscHelpers;
use app\models\Debuginfo;

$this->title = Yii::$app->name . ' - Failed Items';
$this->params['breadcrumbs'][] = 'Failed Items';

$session = Yii::$app->session;
?>

<style>
.cf-failed-section { margin-bottom: 28px; }
.cf-failed-error   { color: #b04a00; font-family: monospace; white-space: pre-wrap;
                     word-break: break-word; max-width: 480px; font-size: 12px; }
.cf-failed-empty   { padding: 14px; color: #888; font-style: italic;
                     border: 1px dashed #ddd; border-radius: 4px; }
.cf-failed-meta    { font-size: 11px; color: #666; }
.cf-failed-section h3 { margin-top: 6px; }
.cf-search-row     { margin-bottom: 8px; }
.cf-search-row .cf-search-input { max-width: 380px; display: inline-block; }
.cf-active-filter  { display: inline-block; margin-left: 8px; padding: 2px 8px;
                     background: #fff3cd; color: #856404; border-radius: 4px;
                     font-size: 11px; }
</style>

<h1>Failed Items</h1>
<p class="cf-failed-meta">
    Crash reports and debug-info files in the current project that the
    daemon could not process. Each row shows the most recent error
    message captured by <code>tbl_processingerror</code>. Use
    <strong>Retry</strong> to re-queue an item for the next daemon poll
    cycle (status flips back to Waiting; the existing error history is
    preserved so you can still see what went wrong).
    Click any column header to sort. The search box matches
    filename, GUID, and any historical error-message text.
</p>

<?php if ($session->hasFlash('failed-retry-success')): ?>
    <div class="alert alert-success">
        <?= Html::encode($session->getFlash('failed-retry-success')) ?>
    </div>
<?php endif; ?>
<?php if ($session->hasFlash('failed-retry-error')): ?>
    <div class="alert alert-danger">
        <?= Html::encode($session->getFlash('failed-retry-error')) ?>
    </div>
<?php endif; ?>

<?php if ($projectId <= 0): ?>
    <div class="alert alert-info">
        Select a project (top of any data page) before browsing failed items.
    </div>
<?php endif; ?>

<!-- ============================== Crash reports ============================== -->
<?php if ($canCrash && $crashProvider !== null): ?>
    <div class="cf-failed-section">
        <h3>Failed crash reports
            <span class="badge badge-danger"><?= (int) $crashProvider->getTotalCount() ?></span>
            <?php if ($crashQ !== ''): ?>
                <span class="cf-active-filter">
                    filter: <strong><?= Html::encode($crashQ) ?></strong>
                </span>
            <?php endif; ?>
        </h3>

        <form method="get" action="" class="cf-search-row form-inline">
            <input type="text"
                   name="cr-q"
                   value="<?= Html::encode($crashQ) ?>"
                   placeholder="Search by filename, GUID, or error message…"
                   class="form-control form-control-sm cf-search-input">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($crashQ !== ''): ?>
                <?= Html::a('Clear',
                    Url::current(['cr-q' => null, 'cr-page' => null, 'cr-sort' => null]),
                    ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            <?php endif; ?>
            <?php // preserve other query params (di-q, di-page, di-sort) ?>
            <?php foreach (['di-q', 'di-page', 'di-sort'] as $k):
                $v = Yii::$app->request->get($k);
                if ($v !== null && $v !== ''): ?>
                <input type="hidden" name="<?= Html::encode($k) ?>" value="<?= Html::encode($v) ?>">
            <?php endif; endforeach; ?>
        </form>

        <?php if ((int) $crashProvider->getTotalCount() === 0): ?>
            <div class="cf-failed-empty">
                <?= $crashQ === ''
                    ? 'No failed crash reports in this project. Healthy.'
                    : 'No failed crash reports match your search.' ?>
            </div>
        <?php else: ?>
            <?= GridView::widget([
                'dataProvider' => $crashProvider,
                'layout'       => "{items}\n{pager}\n{summary}",
                'columns' => [
                    [
                        'attribute' => 'id',
                        'label'     => 'ID',
                        'value'     => function ($d) {
                            return Html::a('#' . (int) $d->id,
                                ['/crash-report/view', 'id' => $d->id]);
                        },
                        'format'         => 'raw',
                        'contentOptions' => ['style' => 'white-space:nowrap; width:60px'],
                    ],
                    [
                        'attribute' => 'srcfilename',
                        'label'     => 'File',
                        'value'     => function ($d) {
                            $fname = $d->srcfilename ?: ('crashguid ' . substr((string) $d->crashguid, 0, 8));
                            return Html::encode($fname);
                        },
                        'format' => 'raw',
                    ],
                    [
                        'attribute' => 'received',
                        'label'     => 'Received',
                        'value'     => function ($d) {
                            $ts = (int) ($d->received ?: $d->date_created);
                            return $ts > 0 ? date('Y-m-d H:i', $ts) : '-';
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:130px'],
                    ],
                    [
                        'attribute' => 'filesize',
                        'label'     => 'Size',
                        'value'     => function ($d) {
                            return MiscHelpers::fileSizeToStr((int) $d->filesize);
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:80px;
                                                      text-align:right'],
                    ],
                    [
                        'header' => 'Reason',
                        'format' => 'raw',
                        'value'  => function ($d) {
                            $msg = (string) ($d->last_error ?? '');
                            if ($msg === '') {
                                return '<span class="text-muted">(no error message recorded)</span>';
                            }
                            return '<span class="cf-failed-error">' . Html::encode($msg) . '</span>';
                        },
                    ],
                    [
                        'header' => 'Action',
                        'format' => 'raw',
                        'value'  => function ($d) {
                            return Html::beginForm(['/site/failed-retry'], 'post',
                                    ['style' => 'display:inline; margin:0;'])
                                . Html::hiddenInput('kind', 'crash')
                                . Html::hiddenInput('id', (int) $d->id)
                                . Html::submitButton('Retry',
                                    ['class' => 'btn btn-sm btn-outline-warning',
                                     'data-confirm' =>
                                        'Re-queue crash report #' . (int) $d->id . '?'])
                                . Html::endForm();
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:80px'],
                    ],
                ],
            ]) ?>
        <?php endif; ?>
    </div>
<?php elseif (!$canCrash): ?>
    <div class="cf-failed-section">
        <h3 class="text-muted">Failed crash reports</h3>
        <div class="cf-failed-empty">
            You don't have permission to browse crash reports in this project.
        </div>
    </div>
<?php endif; ?>

<!-- ============================== Debug-info files ============================== -->
<?php if ($canDebug && $debugProvider !== null): ?>
    <div class="cf-failed-section">
        <h3>Failed debug-info files
            <span class="badge badge-danger"><?= (int) $debugProvider->getTotalCount() ?></span>
            <?php if ($debugQ !== ''): ?>
                <span class="cf-active-filter">
                    filter: <strong><?= Html::encode($debugQ) ?></strong>
                </span>
            <?php endif; ?>
        </h3>

        <form method="get" action="" class="cf-search-row form-inline">
            <input type="text"
                   name="di-q"
                   value="<?= Html::encode($debugQ) ?>"
                   placeholder="Search by filename, GUID, or error message…"
                   class="form-control form-control-sm cf-search-input">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($debugQ !== ''): ?>
                <?= Html::a('Clear',
                    Url::current(['di-q' => null, 'di-page' => null, 'di-sort' => null]),
                    ['class' => 'btn btn-sm btn-outline-secondary']) ?>
            <?php endif; ?>
            <?php foreach (['cr-q', 'cr-page', 'cr-sort'] as $k):
                $v = Yii::$app->request->get($k);
                if ($v !== null && $v !== ''): ?>
                <input type="hidden" name="<?= Html::encode($k) ?>" value="<?= Html::encode($v) ?>">
            <?php endif; endforeach; ?>
        </form>

        <?php if ((int) $debugProvider->getTotalCount() === 0): ?>
            <div class="cf-failed-empty">
                <?= $debugQ === ''
                    ? 'No failed debug-info files in this project. Healthy.'
                    : 'No failed debug-info files match your search.' ?>
            </div>
        <?php else: ?>
            <?= GridView::widget([
                'dataProvider' => $debugProvider,
                'layout'       => "{items}\n{pager}\n{summary}",
                'columns' => [
                    [
                        'attribute' => 'id',
                        'label'     => 'ID',
                        'value'     => function ($d) {
                            return Html::a('#' . (int) $d->id,
                                ['/debug-info/view', 'id' => $d->id]);
                        },
                        'format'         => 'raw',
                        'contentOptions' => ['style' => 'white-space:nowrap; width:60px'],
                    ],
                    [
                        'attribute' => 'filename',
                        'label'     => 'File',
                        'value'     => function ($d) {
                            return Html::encode((string) $d->filename);
                        },
                        'format' => 'raw',
                    ],
                    [
                        'header' => 'Format',
                        'value'  => function ($d) {
                            return method_exists($d, 'getFormatStr')
                                ? $d->getFormatStr()
                                : ($d->format ?: 'detecting…');
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:120px'],
                    ],
                    [
                        'attribute' => 'status',
                        'label'     => 'Status',
                        'value'     => function ($d) {
                            $s = (int) $d->status;
                            if ($s === 4) return 'Invalid';
                            if (defined(Debuginfo::class . '::STATUS_UNSUPPORTED_FORMAT')
                                && $s === Debuginfo::STATUS_UNSUPPORTED_FORMAT) {
                                return 'Unsupported format';
                            }
                            return 'status ' . $s;
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:140px'],
                    ],
                    [
                        'attribute' => 'dateuploaded',
                        'label'     => 'Uploaded',
                        'value'     => function ($d) {
                            return (int) $d->dateuploaded > 0
                                ? date('Y-m-d H:i', (int) $d->dateuploaded)
                                : '-';
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:130px'],
                    ],
                    [
                        'header' => 'Reason',
                        'format' => 'raw',
                        'value'  => function ($d) {
                            $msg = (string) ($d->last_error ?? '');
                            if ($msg === '') {
                                return '<span class="text-muted">(no error message recorded)</span>';
                            }
                            return '<span class="cf-failed-error">' . Html::encode($msg) . '</span>';
                        },
                    ],
                    [
                        'header' => 'Action',
                        'format' => 'raw',
                        'value'  => function ($d) {
                            return Html::beginForm(['/site/failed-retry'], 'post',
                                    ['style' => 'display:inline; margin:0;'])
                                . Html::hiddenInput('kind', 'debug')
                                . Html::hiddenInput('id', (int) $d->id)
                                . Html::submitButton('Retry',
                                    ['class' => 'btn btn-sm btn-outline-warning',
                                     'data-confirm' =>
                                        'Re-queue debug-info file #' . (int) $d->id . '?'])
                                . Html::endForm();
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:80px'],
                    ],
                ],
            ]) ?>
        <?php endif; ?>
    </div>
<?php elseif (!$canDebug): ?>
    <div class="cf-failed-section">
        <h3 class="text-muted">Failed debug-info files</h3>
        <div class="cf-failed-empty">
            You don't have permission to browse debug-info files in this project.
        </div>
    </div>
<?php endif; ?>
