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

// Preserve cross-section query params on submit so searching one
// grid does not clobber pagination/sort on the other.
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
.flash-success     { padding: 8px 12px; background: #dff0d8; color: #2c662d;
                     border: 1px solid #b6dfb1; border-radius: 4px; margin-bottom: 10px; }
.flash-error       { padding: 8px 12px; background: #f2dede; color: #952422;
                     border: 1px solid #ebccd1; border-radius: 4px; margin-bottom: 10px; }
</style>

<div class="subheader">Failed Reports</div>

<p class="cf-failed-meta">
    Crash reports and debug-info files in the current project that the daemon
    could not process. Each row shows the most recent error message captured by
    <code>tbl_processingerror</code>. Use <strong>Retry</strong> to re-queue an
    item for the next daemon poll cycle (status flips back to Pending; the
    existing error history is preserved so you can still see what went wrong).
    Click any column header to sort. The search box matches filename, GUID,
    and any historical error-message text.
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

<!-- ============================== Crash reports ============================== -->
<?php if($canCrash && $crashProvider !== null): ?>
    <div class="cf-failed-section span-26 last">
        <h3>Failed crash reports
            <span style="color:#a00;">(<?php echo (int)$crashTotal; ?>)</span>
            <?php if($crashQ !== ''): ?>
                <span class="cf-active-filter">
                    filter: <strong><?php echo CHtml::encode($crashQ); ?></strong>
                </span>
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
            <?php $this->widget('zii.widgets.grid.CGridView', array(
                'dataProvider' => $crashProvider,
                'selectableRows' => null,
                'template' => "{items}\n{pager}\n{summary}",
                'columns' => array(
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
                        'value'  => '
                            CHtml::form(Yii::app()->createUrl("site/failedRetry"), "post",
                                array("style"=>"display:inline; margin:0;"))
                            . CHtml::hiddenField("kind", "crash")
                            . CHtml::hiddenField("id", (int)$data->id)
                            . CHtml::submitButton("Retry",
                                array("class"=>"cf-retry-btn",
                                      "confirm"=>"Re-queue crash report #".(int)$data->id."?"))
                            . CHtml::endForm()
                        ',
                    ),
                ),
            )); ?>
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
<?php if($canDebug && $debugProvider !== null): ?>
    <div class="cf-failed-section span-26 last">
        <h3>Failed debug-info files
            <span style="color:#a00;">(<?php echo (int)$debugTotal; ?>)</span>
            <?php if($debugQ !== ''): ?>
                <span class="cf-active-filter">
                    filter: <strong><?php echo CHtml::encode($debugQ); ?></strong>
                </span>
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
            <?php $this->widget('zii.widgets.grid.CGridView', array(
                'dataProvider' => $debugProvider,
                'selectableRows' => null,
                'template' => "{items}\n{pager}\n{summary}",
                'columns' => array(
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
                        'value'  => '
                            CHtml::form(Yii::app()->createUrl("site/failedRetry"), "post",
                                array("style"=>"display:inline; margin:0;"))
                            . CHtml::hiddenField("kind", "debug")
                            . CHtml::hiddenField("id", (int)$data->id)
                            . CHtml::submitButton("Retry",
                                array("class"=>"cf-retry-btn",
                                      "confirm"=>"Re-queue debug info file #".(int)$data->id."?"))
                            . CHtml::endForm()
                        ',
                    ),
                ),
            )); ?>
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
