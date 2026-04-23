<?php

/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int|null $groupid Optional crash-group id when this list is rendered inside a group view */

use yii\grid\GridView;
use yii\grid\CheckboxColumn;
use yii\helpers\Html;
use yii\helpers\Url;
use app\components\MiscHelpers;

$groupid = $groupid ?? null;
$canManage = (bool) Yii::$app->user->can('pperm_manage_crash_reports');

// Each call carries the crash-group id when present so the bulk
// operations stay scoped to that group; otherwise they apply to
// the current project + version.
$urlParams = $groupid !== null ? ['groupid' => $groupid] : [];
$deleteMultipleUrl    = Url::to(array_merge(['/crash-report/delete-multiple'],    $urlParams));
$reprocessMultipleUrl = Url::to(array_merge(['/crash-report/reprocess-multiple'], $urlParams));
$reprocessAllUrl      = Url::to(array_merge(['/crash-report/reprocess-all'],      $urlParams));
$deleteAllByVerUrl     = Url::to(['/crash-report/delete-all-by-ver']);
$deleteAllBeforeVerUrl = Url::to(['/crash-report/delete-all-before-ver']);
$packAllByVerUrl       = Url::to(['/crash-report/pack-all-by-ver']);
$uploadFileUrl        = Url::to(['/crash-report/upload-file']);

// Whether the user has chosen a specific version (vs the "All
// versions" sentinel -1). The "Delete all reports BEFORE current
// version" item only makes sense for a specific version.
$curProjVer = (int) Yii::$app->user->getCurProjectVer();
$specificVerSelected = $curProjVer !== -1;

$totalRows = (int) $dataProvider->getTotalCount();
$gridId    = 'cf-grid-crashreports';
$formId    = 'cf-bulk-form-crashreports';
?>

<style>
.cf-actions-bar    { display: flex; align-items: center; gap: 12px;
                     margin: 6px 0 8px 0; font-size: 12px; flex-wrap: wrap; }
.cf-bulk-count     { color: #666; min-width: 110px; }
</style>

<?php echo Html::beginForm($reprocessMultipleUrl, 'post',
    ['id' => $formId, 'data-grid-id' => $gridId]); ?>

    <!-- Same shape as the Failed Items toolbar so all bulk actions
         go through one form; per-button data-action overrides the
         submit URL on click. -->
    <div class="cf-actions-bar">
        <span class="cf-bulk-count" data-counter="<?= $gridId ?>">0 selected</span>

        <?php if ($canManage): ?>
            <?= Html::a('Upload New File', $uploadFileUrl,
                ['class' => 'btn btn-sm btn-primary']) ?>

            <div class="dropdown">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary dropdown-toggle"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Other Actions...
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item cf-bulk-link"
                       href="#"
                       data-form="<?= $formId ?>"
                       data-action="<?= Html::encode($deleteMultipleUrl) ?>"
                       data-mode="selected"
                       data-confirm-prefix="PERMANENTLY DELETE"
                       data-kind-label="crash report">Delete Selected Reports</a>
                    <a class="dropdown-item cf-bulk-link"
                       href="#"
                       data-form="<?= $formId ?>"
                       data-action="<?= Html::encode($reprocessMultipleUrl) ?>"
                       data-mode="selected"
                       data-confirm-prefix="Reprocess"
                       data-kind-label="crash report">Reprocess Selected Reports</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item cf-bulk-link"
                       href="#"
                       data-form="<?= $formId ?>"
                       data-action="<?= Html::encode($reprocessAllUrl) ?>"
                       data-mode="all"
                       data-confirm-msg="WARNING: this may take a long time. Reprocess ALL <?= $totalRows ?> crash reports <?= $groupid !== null ? 'in this collection' : 'in the currently selected project version' ?>?">
                        Reprocess All Reports<?= $groupid !== null ? ' In Collection' : '' ?>
                    </a>

                    <?php if ($groupid === null): ?>
                        <?php // The bulk-by-version actions are intentionally
                              // NOT shown inside a Crash Group view (the group
                              // already implies a scope; mixing per-version
                              // bulk delete on top would be confusing). ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item cf-bulk-link text-danger"
                           href="#"
                           data-form="<?= $formId ?>"
                           data-action="<?= Html::encode($deleteAllByVerUrl) ?>"
                           data-mode="all"
                           data-confirm-msg="WARNING: this PERMANENTLY DELETES every crash report <?= $specificVerSelected ? 'of the currently selected version' : 'in the entire project (you have All versions selected)' ?>. The on-disk .zip files will also be removed. This cannot be undone. Continue?">
                            Delete All Reports Of Current Version
                        </a>
                        <a class="dropdown-item cf-bulk-link text-danger
                                  <?= $specificVerSelected ? '' : 'disabled' ?>"
                           href="#"
                           <?php if ($specificVerSelected): ?>
                           data-form="<?= $formId ?>"
                           data-action="<?= Html::encode($deleteAllBeforeVerUrl) ?>"
                           data-mode="all"
                           data-confirm-msg="WARNING: this PERMANENTLY DELETES every crash report whose version is older than the currently selected one, AND removes those AppVersion rows. Cannot be undone. Continue?"
                           <?php else: ?>
                           tabindex="-1"
                           aria-disabled="true"
                           title="Pick a specific version (not All) to use this action"
                           <?php endif; ?>>
                            Delete All Reports Before Current Version
                        </a>
                        <a class="dropdown-item cf-bulk-link"
                           href="#"
                           data-form="<?= $formId ?>"
                           data-action="<?= Html::encode($packAllByVerUrl) ?>"
                           data-mode="all"
                           data-confirm-msg="Copy all <?= $totalRows ?> crash reports <?= $specificVerSelected ? 'of the currently selected version' : '(All versions)' ?> into a dated pack directory under /data/packedReports/? You'll receive an email when the pack is ready.">
                            Pack Reports Files Of Current Version
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid-view">
        <?= GridView::widget([
            'id'           => $gridId,
            'dataProvider' => $dataProvider,
            'columns' => [
                [
                    'class' => CheckboxColumn::class,
                    'name'  => 'DeleteRows',  // matches Yii1 form-field name so any
                                              // existing selenium / curl scripts that
                                              // post DeleteRows[] keep working
                    'checkboxOptions' => function ($d) {
                        return ['value' => (int) $d->id, 'class' => 'cf-row-checkbox'];
                    },
                ],
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

<?php echo Html::endForm(); ?>

<?php
// JS handler shared with the Failed Items pattern. Counts selected
// rows, drives the confirm() text, and for "all" mode strips the
// DeleteRows[] payload before submit so the controller takes the
// reprocess-all path even if checkboxes happened to be ticked.
$this->registerJs(<<<'JS'
(function () {
    function updateCounter(form) {
        var gridId = form.getAttribute('data-grid-id');
        var counter = document.querySelector('[data-counter="' + gridId + '"]');
        var grid = document.getElementById(gridId);
        if (!grid || !counter) return;
        var n = grid.querySelectorAll('input.cf-row-checkbox:checked').length;
        counter.textContent = n + ' selected';
    }

    document.querySelectorAll('form[data-grid-id]').forEach(function (form) {
        var grid = document.getElementById(form.getAttribute('data-grid-id'));
        if (grid) {
            grid.addEventListener('change', function () {
                setTimeout(function () { updateCounter(form); }, 0);
            });
        }
        updateCounter(form);
    });

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

        var msg;
        if (mode === 'all') {
            msg = link.getAttribute('data-confirm-msg') || 'Are you sure?';
            // Strip selected rows so controller takes the reprocess-all path.
            if (grid) {
                grid.querySelectorAll('input.cf-row-checkbox:checked').forEach(function (cb) {
                    cb.checked = false;
                });
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
        }
        if (!confirm(msg)) return;
        form.submit();
    });
})();
JS
);
?>
