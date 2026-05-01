<?php 
    $this->pageTitle = Yii::app()->name . " - View Debug Info File #".CHtml::encode($model->id); 
    $this->breadcrumbs=array(
	'Debug Info'=>array('index'),
	'Debug Info File #'.CHtml::encode($model->id),
);
$symbolTestInput = isset($symbolTestInput) ? $symbolTestInput : '';
$symbolTestResults = isset($symbolTestResults) ? $symbolTestResults : array();
$symbolTestError = isset($symbolTestError) ? $symbolTestError : '';
?>

<?php
$processingErrors = $model->getProcessingErrors();
if(count($processingErrors)>0):	
?>

<div class="span-18 last">
    <div class="flash-error">
        There were some processing errors:
    <ul class="processing-errors">
        <?php 
            foreach($processingErrors as $error)
            {
                echo '<li>'.CHtml::encode($error->message).'</li>';
            }
        ?>
    </ul>	
    </div>
</div>

<?php endif;?>

<!-- Human-readable extraction summary -->
<div class="span-18 last">
	<div style="border: 1px solid #d7d7d7; background: #fafafa; padding: 12px 14px; margin: 0 0 14px 0;">
		<h4 style="margin-top: 0;">What the importer extracted</h4>
		<p class="hint" style="margin-top: 0;">
			This summarizes the fields produced when the CrashFix daemon imported this file. Use it to verify that
			format, architecture, and build id match the binaries in your crash dumps.
		</p>
		<dl style="margin-bottom: 0;">
			<?php foreach($model->getExtractionSummaryDlItems() as $row): ?>
				<dt style="font-weight: bold; margin-top: 8px;"><?php echo CHtml::encode($row['term']); ?></dt>
				<dd style="margin-left: 18px;"><?php echo nl2br(CHtml::encode($row['description']), false); ?></dd>
			<?php endforeach; ?>
		</dl>

		<hr />
		<h4>Test address resolution</h4>
		<p class="hint">
			Paste one or more raw stack offsets / RVAs (for example <code>0x77298d</code> or
			<code>EasyJtag.exe!+0x77298d</code>). CrashFix will run addr2line against this exact
			uploaded file and compare raw input with image-base adjusted addresses.
		</p>
		<?php echo CHtml::beginForm($this->createUrl('/debugInfo/testResolve', array('id'=>$model->id)), 'post'); ?>
			<?php echo CHtml::textArea('SymbolTest[addresses]', $symbolTestInput, array(
				'rows'=>3,
				'style'=>'width: 98%; font-family: monospace;',
				'placeholder'=>'0x77298d 0x88c047 0xe5ea5',
			)); ?>
			<div style="margin-top: 8px;">
				<?php echo CHtml::submitButton('Test address resolution'); ?>
			</div>
		<?php echo CHtml::endForm(); ?>

		<?php if($symbolTestError !== ''): ?>
			<div class="flash-error" style="margin-top: 10px;"><?php echo CHtml::encode($symbolTestError); ?></div>
		<?php endif; ?>

		<?php if(count($symbolTestResults) > 0): ?>
			<table class="items" style="margin-top: 10px; width: 100%;">
				<thead>
					<tr>
						<th>Input</th>
						<th>Candidate</th>
						<th>Address</th>
						<th>Symbol</th>
						<th>File / line</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach($symbolTestResults as $result): ?>
					<tr>
						<td colspan="5" style="background: #f0f0f0;">
							<strong><?php echo CHtml::encode($result['input']); ?></strong>
							<span class="hint">
								tool: <?php echo CHtml::encode($result['tool']); ?>,
								image base: <?php echo CHtml::encode($result['imageBase']); ?>
							</span>
						</td>
					</tr>
					<?php foreach($result['candidates'] as $candidate): ?>
						<tr>
							<td><?php echo CHtml::encode($result['input']); ?></td>
							<td><?php echo CHtml::encode($candidate['label']); ?></td>
							<td><code><?php echo CHtml::encode($candidate['address']); ?></code></td>
							<td>
								<?php if($candidate['resolved']): ?>
									<strong><?php echo CHtml::encode($candidate['symbol']); ?></strong>
								<?php else: ?>
									<span class="hint"><?php echo CHtml::encode($candidate['symbol'] !== '' ? $candidate['symbol'] : 'not resolved'); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo CHtml::encode($candidate['fileLine']); ?>
								<?php if($candidate['error'] !== ''): ?>
									<br /><span class="hint"><?php echo nl2br(CHtml::encode($candidate['error']), false); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Actions Toolbar -->
<div class="span-18 last">
	<div class="div_actions">
		<?php echo CHtml::form(); ?>		
		<?php echo CHtml::hiddenField('id', $model->id); ?>		
		<span class="hint">Download disabled after upload.</span>
		<?php echo CHtml::link('Delete File', $this->createUrl('/debugInfo/delete', array('id'=>$model->id,)) ); ?>
		<?php echo CHtml::endForm(); ?>	
	</div>	
</div>

<!-- Detail View -->
<div class="span-18 last">

<h4 style="margin-top: 14px;">Symbol Format</h4>
<?php $this->widget('zii.widgets.CDetailView', array(
	'data'=>$model,
	'attributes'=>array(
		array(
			'name'  => 'format',
			'label' => 'Format',
			'type'  => 'text',
			'value' => $model->getFormatStr(),
		),
		array(
			'name'  => 'container',
			'label' => 'Container',
			'type'  => 'text',
			'value' => isset($model->container) && $model->container!=='' ? $model->container : 'unknown',
		),
		array(
			'name'  => 'architecture',
			'label' => 'Architecture',
			'type'  => 'text',
			'value' => isset($model->architecture) && $model->architecture!=='' ? $model->architecture : 'unknown',
		),
		array(
			'name'  => 'has_source_lines',
			'label' => 'Has source lines',
			'type'  => 'text',
			'value' => $model->getHasSourceLinesStr(),
		),
		array(
			'name'  => 'guid',
			'label' => $model->getBuildIdLabel(),
			'type'  => 'text',
			'value' => $model->getBuildIdValue(),
		),
	),
	));?>

<h4 style="margin-top: 14px;">File</h4>
<?php $this->widget('zii.widgets.CDetailView', array(
	'data'=>$model,
	'attributes'=>array(		
		array(  
            'name'=>'dateuploaded',
			'type'=>'text',
            'value'=>date("d/m/y H:i", $model->dateuploaded),
        ),
		'filename',
		array(  
            'name'=>'status',
            'type'=>'text',
            'value'=>$model->getStatusStr(),
        ),
		array(  
            'name'=>'filesize',
            'type'=>'raw',
            'value'=>CHtml::encode(MiscHelpers::fileSizeToStr($model->filesize)),
        ),
		'md5',				
	),
	));?>

