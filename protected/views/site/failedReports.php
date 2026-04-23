<?php
/* @var $this  SiteController */
/* @var $crashProvider CActiveDataProvider|null */
/* @var $debugProvider CActiveDataProvider|null */
/* @var $crashTotal int */
/* @var $debugTotal int */
/* @var $projectId  int */
/* @var $canCrash   bool */
/* @var $canDebug   bool */
/* @var $crashQ     string */
/* @var $debugQ     string */

$this->pageTitle = Yii::app()->name . ' - Failed Reports';
$this->breadcrumbs = array('Failed Reports');

$req = Yii::app()->request;
$preserve = function(array $exclude) use ($req) {
    $out = '';
    foreach (array('cr-q', 'cr-page', 'cr-sort', 'di-q', 'di-page', 'di-sort') as $k) {
        if (in_array($k, $exclude, true)) continue;
        $v = $req->getParam($k);
        if ($v === null || $v === '') continue;
        $out .= CHtml::hiddenField($k, $v);
    }
    return $out;
};

$retryUrl  = $this->createUrl('site/failedRetry');
$deleteUrl = $this->createUrl('site/failedDelete');
$returnTo  = $req->requestUri;
?>

<style>
.cf-failed-section { margin-bottom: 28px; }
.cf-failed-error   { color: #b04a00; font-family: monospace; white-space: pre-wrap;
                     word-break: break-word; max-width: 480px; font-size: 12px; }
.cf-failed-empty   { padding: 14px; color: #888; font-style: italic;
                     border: 1px dashed #ddd; border-radius: 4px; }
.cf-failed-meta    { font-size: 11px; color: #666; }
.cf-failed-section h3 { margin-top: 6px; }
.cf-retry-btn      { padding: 2px 8px; font-size: 11px; }
.cf-search-row     { margin-bottom: 8px; }
.cf-search-row input[type=text] { width: 380px; padding: 3px 6px; }
.cf-search-row button,
.cf-search-row a.cf-clear { padding: 3px 10px; font-size: 12px; }
.cf-active-filter  { display: inline-block; margin-left: 8px; padding: 2px 8px;
                     background: #fff3cd; color: #856404; border-radius: 4px;
                     font-size: 11px; }
/* Selection counter shown to the LEFT of the Other Actions dropdown,
   matching the existing CrashReport _reportList.php toolbar layout. */
.cf-bulk-count     { display: inline-block; min-width: 110px;
                     margin-right: 12px; color: #666; font-size: 12px; }
.flash-success     { padding: 8px 12px; background: #dff0d8; color: #2c662d;
                     border: 1px solid #b6dfb1; border-radius: 4px; margin-bottom: 10px; }
.flash-error       { padding: 8px 12px; background: #f2dede; color: #952422;
                     border: 1px solid #ebccd1; border-radius: 4px; margin-bottom: 10px; }
</style>

<div class="subheader">Failed Reports</div>

<p class="cf-failed-meta">
    Crash reports and debug-info files in the current project that the daemon
    could not process. Click any column header to sort. The search box matches
    filename, GUID, and any historical error-message text. Use the checkboxes
    plus the bulk action bar to retry or delete in batches; the
    <strong>Retry/Delete ALL matching</strong> buttons operate on every row
    matching the current filter, capped at 500 deletions per click.
</p>

<?php if(Yii::app()->user->hasFlash('failed-retry-success')): ?>
    <div class="flash-success"><?php echo CHtml::encode(Yii::app()->user->getFlash('failed-retry-success')); ?></div>
<?php endif; ?>
<?php if(Yii::app()->user->hasFlash('failed-retry-error')): ?>
    <div class="flash-error"><?php echo CHtml::encode(Yii::app()->user->getFlash('failed-retry-error')); ?></div>
<?php endif; ?>

<?php if($projectId <= 0): ?>
    <div class="flash-notice">Select a project (top of any data page) before browsing failed items.</div>
<?php endif; ?>

<?php
// When BOTH grids are visible AND empty AND no search filter is active,
// collapse the per-section empty messaging into a single confirmation.
// Without this the page renders two visually-identical empty-state
// blocks ('No failed crash reports' / 'No failed debug-info files'),
// which reads as duplicated content.
$noFilters     = ($crashQ === '' && $debugQ === '');
$bothShown     = ($canCrash && $crashProvider !== null) && ($canDebug && $debugProvider !== null);
$bothEmpty     = $bothShown && (int)$crashTotal === 0 && (int)$debugTotal === 0;
$collapseEmpty = $bothEmpty && $noFilters;
?>

<?php if($collapseEmpty): ?>
    <div class="cf-failed-section span-26 last"
         style="text-align:center; padding:20px 14px; border:1px solid #b6dfb1;
                background:#dff0d8; color:#2c662d; border-radius:6px;">
        <div style="font-size:32px; line-height:1;">&#x2713;</div>
        <div style="margin-top:8px;"><strong>Everything healthy.</strong></div>
        <div style="font-size:11px; color:#5a7a5a; margin-top:4px;">
            No failed crash reports or debug-info files in this project.
            Items the daemon could not process will appear here for triage.
        </div>
    </div>
<?php endif; ?>

<!-- ============================== Crash reports ============================== -->
<?php if($canCrash && $crashProvider !== null && !$collapseEmpty): ?>
    <div class="cf-failed-section span-26 last">
        <h3>Failed crash reports
            <span style="color:#a00;">(<?php echo (int)$crashTotal; ?>)</span>
            <?php if($crashQ !== ''): ?>
                <span class="cf-active-filter">filter: <strong><?php echo CHtml::encode($crashQ); ?></strong></span>
            <?php endif; ?>
        </h3>

        <div class="cf-search-row">
            <?php echo CHtml::beginForm('', 'get'); ?>
                <?php echo CHtml::textField('cr-q', $crashQ,
                    array('placeholder' => 'Search by filename, GUID, or error message...')); ?>
                <?php echo CHtml::submitButton('Search'); ?>
                <?php if($crashQ !== ''): ?>
                    <?php echo CHtml::link('Clear',
                        $this->createUrl('site/failedReports',
                            array_filter(array(
                                'di-q'    => $req->getParam('di-q'),
                                'di-page' => $req->getParam('di-page'),
                                'di-sort' => $req->getParam('di-sort'),
                            ), 'strlen')),
                        array('class' => 'cf-clear')); ?>
                <?php endif; ?>
                <?php echo $preserve(array('cr-q', 'cr-page', 'cr-sort')); ?>
            <?php echo CHtml::endForm(); ?>
        </div>

        <?php if((int)$crashTotal === 0): ?>
            <div class="cf-failed-empty">
                <?php echo $crashQ === ''
                    ? 'No failed crash reports in this project. Healthy.'
                    : 'No failed crash reports match your search.'; ?>
            </div>
        <?php else: ?>
            <?php echo CHtml::beginForm($retryUrl, 'post', array(
                'id' => 'cf-bulk-form-crash',
                'data-grid-id' => 'cf-grid-crash',
            )); ?>
                <?php echo CHtml::hiddenField('kind',   'crash'); ?>
                <?php echo CHtml::hiddenField('q',      $crashQ); ?>
                <?php echo CHtml::hiddenField('return', $returnTo); ?>
                <?php echo CHtml::hiddenField('all',    '0', array('data-bulk-all'=>'1')); ?>

                <!-- Actions Toolbar: matches the CrashReport _reportList.php
                     layout (.div_actions + .dropdown-menu). 'Other Actions...'
                     contains exactly the three operations the page supports. -->
                <div class="span-27 last">
                    <div class="div_actions">
                        <span class="cf-bulk-count" data-counter="cf-grid-crash">0 selected</span>

                        <ul class="dropdown-menu">
                            <li><a href="#">Other Actions...</a>
                                <ul>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-crash"
                                           data-action="<?php echo CHtml::encode($deleteUrl); ?>"
                                           data-mode="selected"
                                           data-confirm-prefix="PERMANENTLY DELETE"
                                           data-kind-label="crash report">Delete Selected Reports</a></li>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-crash"
                                           data-action="<?php echo CHtml::encode($retryUrl); ?>"
                                           data-mode="selected"
                                           data-confirm-prefix="Reprocess"
                                           data-kind-label="crash report">Reprocess Selected Reports</a></li>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-crash"
                                           data-action="<?php echo CHtml::encode($retryUrl); ?>"
                                           data-mode="all"
                                           data-confirm-msg="Reprocess ALL <?php echo (int)$crashTotal; ?> matching crash reports? They'll be picked up by the daemon on the next poll cycle.">Reprocess All Reports<?php echo $crashQ !== '' ? ' (filtered)' : ''; ?></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php $this->widget('zii.widgets.grid.CGridView', array(
                    'id'             => 'cf-grid-crash',
                    'dataProvider'   => $crashProvider,
                    'selectableRows' => null,
                    'template'       => "{items}\n{pager}\n{summary}",
                    'columns' => array(
                        array(
                            'class'           => 'CCheckBoxColumn',
                            'id'              => 'ids',
                            'value'           => '$data->id',
                            'selectableRows'  => 2,
                            'checkBoxHtmlOptions' => array('class' => 'cf-row-checkbox'),
                        ),
                        array(
                            'name'   => 'id',
                            'header' => 'ID',
                            'type'   => 'raw',
                            'value'  => 'CHtml::link("#".(int)$data->id,
                                            Yii::app()->createUrl("crashReport/view",
                                                array("id"=>$data->id)))',
                            'cssClassExpression' => '"column-right-align"',
                        ),
                        array(
                            'name'   => 'srcfilename',
                            'header' => 'File',
                            'type'   => 'text',
                            'value'  => '$data->srcfilename
                                           ? $data->srcfilename
                                           : ("crashguid ".substr((string)$data->crashguid, 0, 8))',
                        ),
                        array(
                            'name'   => 'received',
                            'header' => 'Received',
                            'type'   => 'text',
                            'value'  => '(int)$data->received > 0
                                           ? date("Y-m-d H:i", (int)$data->received)
                                           : "-"',
                        ),
                        array(
                            'name'   => 'filesize',
                            'header' => 'Size',
                            'type'   => 'text',
                            'value'  => 'MiscHelpers::fileSizeToStr((int)$data->filesize)',
                            'cssClassExpression' => '"column-right-align"',
                        ),
                        array(
                            'header' => 'Reason',
                            'type'   => 'raw',
                            'value'  => 'isset($data->last_error) && (string)$data->last_error !== ""
                                           ? \'<span class="cf-failed-error">\'
                                                . CHtml::encode((string)$data->last_error)
                                                . \'</span>\'
                                           : \'<span style="color:#999;">(no error message recorded)</span>\'',
                        ),
                        array(
                            'header' => 'Action',
                            'type'   => 'raw',
                            // Per-row Retry: a plain (non-submit) button
                            // carrying data-attrs. JS at the bottom of
                            // the page turns the click into a single-row
                            // POST. Avoids nesting a <form> inside this
                            // bulk form (invalid HTML).
                            'value'  => 'CHtml::htmlButton("Retry", array(
                                "type"        => "button",
                                "class"       => "cf-retry-btn cf-row-retry",
                                "data-row-id" => (int)$data->id,
                                "data-confirm"=> "Re-queue crash report #".(int)$data->id."?",
                            ))',
                        ),
                    ),
                )); ?>
            <?php echo CHtml::endForm(); ?>
        <?php endif; ?>
    </div>
<?php elseif(!$canCrash): ?>
    <div class="cf-failed-section span-26 last">
        <h3 style="color:#999;">Failed crash reports</h3>
        <div class="cf-failed-empty">
            You don't have permission to browse crash reports in this project.
        </div>
    </div>
<?php endif; ?>

<!-- ============================== Debug-info files ============================== -->
<?php if($canDebug && $debugProvider !== null && !$collapseEmpty): ?>
    <div class="cf-failed-section span-26 last">
        <h3>Failed debug-info files
            <span style="color:#a00;">(<?php echo (int)$debugTotal; ?>)</span>
            <?php if($debugQ !== ''): ?>
                <span class="cf-active-filter">filter: <strong><?php echo CHtml::encode($debugQ); ?></strong></span>
            <?php endif; ?>
        </h3>

        <div class="cf-search-row">
            <?php echo CHtml::beginForm('', 'get'); ?>
                <?php echo CHtml::textField('di-q', $debugQ,
                    array('placeholder' => 'Search by filename, GUID, or error message...')); ?>
                <?php echo CHtml::submitButton('Search'); ?>
                <?php if($debugQ !== ''): ?>
                    <?php echo CHtml::link('Clear',
                        $this->createUrl('site/failedReports',
                            array_filter(array(
                                'cr-q'    => $req->getParam('cr-q'),
                                'cr-page' => $req->getParam('cr-page'),
                                'cr-sort' => $req->getParam('cr-sort'),
                            ), 'strlen')),
                        array('class' => 'cf-clear')); ?>
                <?php endif; ?>
                <?php echo $preserve(array('di-q', 'di-page', 'di-sort')); ?>
            <?php echo CHtml::endForm(); ?>
        </div>

        <?php if((int)$debugTotal === 0): ?>
            <div class="cf-failed-empty">
                <?php echo $debugQ === ''
                    ? 'No failed debug-info files in this project. Healthy.'
                    : 'No failed debug-info files match your search.'; ?>
            </div>
        <?php else: ?>
            <?php echo CHtml::beginForm($retryUrl, 'post', array(
                'id' => 'cf-bulk-form-debug',
                'data-grid-id' => 'cf-grid-debug',
            )); ?>
                <?php echo CHtml::hiddenField('kind',   'debug'); ?>
                <?php echo CHtml::hiddenField('q',      $debugQ); ?>
                <?php echo CHtml::hiddenField('return', $returnTo); ?>
                <?php echo CHtml::hiddenField('all',    '0', array('data-bulk-all'=>'1')); ?>

                <div class="span-27 last">
                    <div class="div_actions">
                        <span class="cf-bulk-count" data-counter="cf-grid-debug">0 selected</span>

                        <ul class="dropdown-menu">
                            <li><a href="#">Other Actions...</a>
                                <ul>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-debug"
                                           data-action="<?php echo CHtml::encode($deleteUrl); ?>"
                                           data-mode="selected"
                                           data-confirm-prefix="PERMANENTLY DELETE"
                                           data-kind-label="debug-info file">Delete Selected Files</a></li>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-debug"
                                           data-action="<?php echo CHtml::encode($retryUrl); ?>"
                                           data-mode="selected"
                                           data-confirm-prefix="Reprocess"
                                           data-kind-label="debug-info file">Reprocess Selected Files</a></li>
                                    <li><a href="#"
                                           class="cf-bulk-link"
                                           data-form="cf-bulk-form-debug"
                                           data-action="<?php echo CHtml::encode($retryUrl); ?>"
                                           data-mode="all"
                                           data-confirm-msg="Reprocess ALL <?php echo (int)$debugTotal; ?> matching debug-info files? They'll be picked up by the daemon on the next poll cycle.">Reprocess All Files<?php echo $debugQ !== '' ? ' (filtered)' : ''; ?></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php $this->widget('zii.widgets.grid.CGridView', array(
                    'id'             => 'cf-grid-debug',
                    'dataProvider'   => $debugProvider,
                    'selectableRows' => null,
                    'template'       => "{items}\n{pager}\n{summary}",
                    'columns' => array(
                        array(
                            'class'           => 'CCheckBoxColumn',
                            'id'              => 'ids',
                            'value'           => '$data->id',
                            'selectableRows'  => 2,
                            'checkBoxHtmlOptions' => array('class' => 'cf-row-checkbox'),
                        ),
                        array(
                            'name'   => 'id',
                            'header' => 'ID',
                            'type'   => 'raw',
                            'value'  => 'CHtml::link("#".(int)$data->id,
                                            Yii::app()->createUrl("debugInfo/view",
                                                array("id"=>$data->id)))',
                            'cssClassExpression' => '"column-right-align"',
                        ),
                        array(
                            'name'   => 'filename',
                            'header' => 'File',
                            'type'   => 'text',
                            'value'  => '(string)$data->filename',
                        ),
                        array(
                            'header' => 'Format',
                            'type'   => 'text',
                            'value'  => 'method_exists($data, "getFormatStr")
                                           ? $data->getFormatStr()
                                           : ((string)$data->format !== "" ? $data->format : "detecting...")',
                        ),
                        array(
                            'name'   => 'status',
                            'header' => 'Status',
                            'type'   => 'text',
                            'value'  => '$data->getStatusStr()',
                        ),
                        array(
                            'name'   => 'dateuploaded',
                            'header' => 'Uploaded',
                            'type'   => 'text',
                            'value'  => '(int)$data->dateuploaded > 0
                                           ? date("Y-m-d H:i", (int)$data->dateuploaded)
                                           : "-"',
                        ),
                        array(
                            'header' => 'Reason',
                            'type'   => 'raw',
                            'value'  => 'isset($data->last_error) && (string)$data->last_error !== ""
                                           ? \'<span class="cf-failed-error">\'
                                                . CHtml::encode((string)$data->last_error)
                                                . \'</span>\'
                                           : \'<span style="color:#999;">(no error message recorded)</span>\'',
                        ),
                        array(
                            'header' => 'Action',
                            'type'   => 'raw',
                            'value'  => 'CHtml::htmlButton("Retry", array(
                                "type"        => "button",
                                "class"       => "cf-retry-btn cf-row-retry",
                                "data-row-id" => (int)$data->id,
                                "data-confirm"=> "Re-queue debug info file #".(int)$data->id."?",
                            ))',
                        ),
                    ),
                )); ?>
            <?php echo CHtml::endForm(); ?>
        <?php endif; ?>
    </div>
<?php elseif(!$canDebug): ?>
    <div class="cf-failed-section span-26 last">
        <h3 style="color:#999;">Failed debug-info files</h3>
        <div class="cf-failed-empty">
            You don't have permission to browse debug-info files in this project.
        </div>
    </div>
<?php endif; ?>

<?php
// CSRF token isn't enforced by default in Yii1 unless CHttpRequest's
// enableCsrfValidation is on; fall back to empty string when not set
// so the JS-built single-row Retry form posts cleanly either way.
$csrfParam = (Yii::app()->request->csrfTokenName ?? null) ?: 'YII_CSRF_TOKEN';
$csrfToken = Yii::app()->request->enableCsrfValidation
    ? Yii::app()->request->csrfToken : '';
$csrfJsParam = json_encode($csrfParam, JSON_UNESCAPED_SLASHES);
$csrfJsToken = json_encode($csrfToken, JSON_UNESCAPED_SLASHES);
$retryUrlJs  = json_encode($retryUrl, JSON_UNESCAPED_SLASHES);

$script = <<<JS
(function () {
    var CSRF_PARAM = $csrfJsParam;
    var CSRF_TOKEN = $csrfJsToken;
    var RETRY_URL  = $retryUrlJs;

    // Selection counter (no enable/disable buttons - the dropdown
    // items always render; we instead alert() when "Selected" is
    // chosen with zero rows ticked).
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

    // Other Actions dropdown items. Each <a class="cf-bulk-link">
    // carries:
    //   data-form              : form id to POST
    //   data-action            : URL to POST to
    //   data-mode              : "selected" | "all"
    //   data-confirm-prefix    : verb for selected-mode confirm ("Reprocess", "PERMANENTLY DELETE")
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

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.cf-row-retry');
        if (!btn) return;
        e.preventDefault();
        var msg = btn.getAttribute('data-confirm') || 'Re-queue this item?';
        if (!confirm(msg)) return;
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
        if (CSRF_TOKEN) add(CSRF_PARAM, CSRF_TOKEN);
        add('kind', kind);
        add('id',   rowId);
        document.body.appendChild(f);
        f.submit();
    });
})();
JS;
Yii::app()->getClientScript()->registerScript('cf-failed-bulk', $script, CClientScript::POS_READY);
?>
