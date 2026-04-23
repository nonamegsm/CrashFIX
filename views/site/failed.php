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
use yii\grid\CheckboxColumn;
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
.cf-actions-bar    { display: flex; align-items: center; gap: 12px;
                     margin: 6px 0 8px 0; font-size: 12px; }
.cf-bulk-count     { color: #666; min-width: 110px; }
</style>

<?php // The AdminLTE layout already renders $this->title as the page
      // header (see views/layouts/adminlte/content.php). Adding an H1
      // here too would show the same title twice. The intro paragraph
      // is sufficient context. ?>
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
            <?php
                $crashTotal = (int) $crashProvider->getTotalCount();
                $crashReturn = (string) Yii::$app->request->url;
            ?>
            <?= Html::beginForm(['/site/failed-retry'], 'post',
                    ['id' => 'cf-bulk-form-crash', 'data-grid-id' => 'cf-grid-crash']) ?>
            <?= Html::hiddenInput('kind', 'crash') ?>
            <?= Html::hiddenInput('q',    $crashQ) ?>
            <?= Html::hiddenInput('return', $crashReturn) ?>
            <?= Html::hiddenInput('all',  '0', ['data-bulk-all' => '1']) ?>

            <!-- Other Actions dropdown - mirrors the Yii1 Crash Reports
                 toolbar pattern. Three items: Delete Selected,
                 Reprocess Selected, Reprocess All (filter-aware). -->
            <div class="cf-actions-bar">
                <span class="cf-bulk-count" data-counter="cf-grid-crash">0 selected</span>
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary dropdown-toggle"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Other Actions...
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-crash"
                           data-action="<?= Url::to(['/site/failed-delete']) ?>"
                           data-mode="selected"
                           data-confirm-prefix="PERMANENTLY DELETE"
                           data-kind-label="crash report">Delete Selected Reports</a>
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-crash"
                           data-action="<?= Url::to(['/site/failed-retry']) ?>"
                           data-mode="selected"
                           data-confirm-prefix="Reprocess"
                           data-kind-label="crash report">Reprocess Selected Reports</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-crash"
                           data-action="<?= Url::to(['/site/failed-retry']) ?>"
                           data-mode="all"
                           data-confirm-msg="Reprocess ALL <?= $crashTotal ?> matching crash reports? They'll be picked up by the daemon on the next poll cycle.">Reprocess All Reports<?= $crashQ !== '' ? ' (filtered)' : '' ?></a>
                    </div>
                </div>
            </div>

            <?= GridView::widget([
                'id'           => 'cf-grid-crash',
                'dataProvider' => $crashProvider,
                'layout'       => "{items}\n{pager}\n{summary}",
                'columns' => [
                    [
                        'class' => CheckboxColumn::class,
                        'name'  => 'ids',
                        'checkboxOptions' => function ($d) {
                            return ['value' => (int) $d->id, 'class' => 'cf-row-checkbox'];
                        },
                    ],
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
                            // Per-row Retry: a plain (non-submit) button
                            // carrying data-attrs. JS at the bottom of the
                            // page turns the click into a single-row POST
                            // to /site/failed-retry. This avoids nesting
                            // a <form> inside the surrounding bulk form
                            // (invalid HTML).
                            return Html::button('Retry', [
                                'type'  => 'button',
                                'class' => 'btn btn-sm btn-outline-warning cf-row-retry',
                                'data-row-id' => (int) $d->id,
                                'data-confirm' =>
                                    'Re-queue crash report #' . (int) $d->id . '?',
                            ]);
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:80px'],
                    ],
                ],
            ]) ?>
            <?= Html::endForm() ?>
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
            <?php
                $debugTotal  = (int) $debugProvider->getTotalCount();
                $debugReturn = (string) Yii::$app->request->url;
            ?>
            <?= Html::beginForm(['/site/failed-retry'], 'post',
                    ['id' => 'cf-bulk-form-debug', 'data-grid-id' => 'cf-grid-debug']) ?>
            <?= Html::hiddenInput('kind',   'debug') ?>
            <?= Html::hiddenInput('q',      $debugQ) ?>
            <?= Html::hiddenInput('return', $debugReturn) ?>
            <?= Html::hiddenInput('all',    '0', ['data-bulk-all' => '1']) ?>

            <div class="cf-actions-bar">
                <span class="cf-bulk-count" data-counter="cf-grid-debug">0 selected</span>
                <div class="dropdown">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary dropdown-toggle"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Other Actions...
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-debug"
                           data-action="<?= Url::to(['/site/failed-delete']) ?>"
                           data-mode="selected"
                           data-confirm-prefix="PERMANENTLY DELETE"
                           data-kind-label="debug-info file">Delete Selected Files</a>
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-debug"
                           data-action="<?= Url::to(['/site/failed-retry']) ?>"
                           data-mode="selected"
                           data-confirm-prefix="Reprocess"
                           data-kind-label="debug-info file">Reprocess Selected Files</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="cf-bulk-form-debug"
                           data-action="<?= Url::to(['/site/failed-retry']) ?>"
                           data-mode="all"
                           data-confirm-msg="Reprocess ALL <?= $debugTotal ?> matching debug-info files? They'll be picked up by the daemon on the next poll cycle.">Reprocess All Files<?= $debugQ !== '' ? ' (filtered)' : '' ?></a>
                    </div>
                </div>
            </div>

            <?= GridView::widget([
                'id'           => 'cf-grid-debug',
                'dataProvider' => $debugProvider,
                'layout'       => "{items}\n{pager}\n{summary}",
                'columns' => [
                    [
                        'class' => CheckboxColumn::class,
                        'name'  => 'ids',
                        'checkboxOptions' => function ($d) {
                            return ['value' => (int) $d->id, 'class' => 'cf-row-checkbox'];
                        },
                    ],
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
                            return Html::button('Retry', [
                                'type'  => 'button',
                                'class' => 'btn btn-sm btn-outline-warning cf-row-retry',
                                'data-row-id' => (int) $d->id,
                                'data-confirm' =>
                                    'Re-queue debug-info file #' . (int) $d->id . '?',
                            ]);
                        },
                        'contentOptions' => ['style' => 'white-space:nowrap; width:80px'],
                    ],
                ],
            ]) ?>
            <?= Html::endForm() ?>
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

<?php
// CSRF token for the dynamically-built per-row Retry forms.
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
$csrfJsParam = json_encode($csrfParam, JSON_UNESCAPED_SLASHES);
$csrfJsToken = json_encode($csrfToken, JSON_UNESCAPED_SLASHES);
$retryUrlJs  = json_encode(\yii\helpers\Url::to(['/site/failed-retry']), JSON_UNESCAPED_SLASHES);

$this->registerJs(<<<JS
(function () {
    var CSRF_PARAM = $csrfJsParam;
    var CSRF_TOKEN = $csrfJsToken;
    var RETRY_URL  = $retryUrlJs;

    // ---------------- Selection counter ------------------------------------
    function updateCounter(form) {
        var gridId = form.getAttribute('data-grid-id');
        var counter = document.querySelector('[data-counter="' + gridId + '"]');
        var grid = document.getElementById(gridId);
        if (!grid || !counter) return;
        var n = grid.querySelectorAll('input.cf-row-checkbox:checked').length;
        counter.textContent = n + ' selected';
    }

    var bulkForms = document.querySelectorAll('form[data-grid-id]');
    for (var i = 0; i < bulkForms.length; i++) {
        var form = bulkForms[i];
        var grid = document.getElementById(form.getAttribute('data-grid-id'));
        if (grid) {
            grid.addEventListener('change', (function (f) {
                return function (e) {
                    setTimeout(function () { updateCounter(f); }, 0);
                };
            })(form));
        }
        updateCounter(form);
    }

    // ---------------- Other Actions dropdown items -------------------------
    // Each <a class="cf-bulk-link"> in the dropdown carries:
    //   data-form              : form id to POST
    //   data-action            : URL to POST to
    //   data-mode              : "selected" | "all"
    //   data-confirm-prefix    : verb for selected-mode confirm
    //   data-kind-label        : "crash report" / "debug-info file"
    //   data-confirm-msg       : full confirm() text for all-mode
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('a.cf-bulk-link');
        if (!link) return;
        e.preventDefault();

        var form = document.getElementById(link.getAttribute('data-form'));
        if (!form) return;
        var grid = document.getElementById(form.getAttribute('data-grid-id'));
        var mode = link.getAttribute('data-mode');

        var action = link.getAttribute('data-action');
        if (action) form.setAttribute('action', action);
        var hidAll = form.querySelector('[data-bulk-all]');

        var msg;
        if (mode === 'all') {
            msg = link.getAttribute('data-confirm-msg') || 'Are you sure?';
            if (hidAll) hidAll.value = '1';
            // Strip selected ids so controller takes the all=1 path
            // (otherwise the ids[] payload would override our intent).
            if (grid) {
                var cb = grid.querySelectorAll('input.cf-row-checkbox:checked');
                for (var k = 0; k < cb.length; k++) cb[k].checked = false;
            }
        } else {
            var n = grid ? grid.querySelectorAll('input.cf-row-checkbox:checked').length : 0;
            if (n === 0) {
                alert('Select one or more rows first.');
                return;
            }
            var prefix = link.getAttribute('data-confirm-prefix') || 'Process';
            var label  = link.getAttribute('data-kind-label')     || 'item';
            msg = prefix + ' ' + n + ' selected ' + label + (n === 1 ? '' : 's') + '?';
            if (hidAll) hidAll.value = '0';
        }
        if (!confirm(msg)) return;
        form.submit();
    });

    // ---------------- Per-row Retry click handler --------------------------
    // The button is a plain <button type="button">; we synthesise a tiny
    // form on click and submit it. Avoids nesting a <form> inside the
    // bulk form (invalid HTML).
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cf-row-retry');
        if (!btn) return;
        e.preventDefault();
        var msg = btn.getAttribute('data-confirm') || 'Re-queue this item?';
        if (!confirm(msg)) return;

        // Determine kind from the surrounding bulk form (crash or debug).
        var bulk = btn.closest('form[data-grid-id]');
        var kind = bulk ? bulk.querySelector('input[name="kind"]').value : '';
        var rowId = btn.getAttribute('data-row-id');

        var f = document.createElement('form');
        f.method = 'post';
        f.action = RETRY_URL;
        f.style.display = 'none';
        function add(name, value) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = name; i.value = value;
            f.appendChild(i);
        }
        add(CSRF_PARAM, CSRF_TOKEN);
        add('kind', kind);
        add('id',   rowId);
        document.body.appendChild(f);
        f.submit();
    });
})();
JS
);
?>
