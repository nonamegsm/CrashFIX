<?php 
    $this->pageTitle = Yii::app()->name . " - View Debug Info File #".CHtml::encode($model->id); 
    $this->breadcrumbs=array(
	'Debug Info'=>array('index'),
	'Debug Info File #'.CHtml::encode($model->id),
);
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

<!-- Actions Toolbar -->
<div class="span-18 last">
	<div class="div_actions">
		<?php echo CHtml::form(); ?>		
		<?php echo CHtml::hiddenField('id', $model->id); ?>		
		<?php echo CHtml::link('Download File', $this->createUrl('/debugInfo/download', array('id'=>$model->id,)) ); ?>
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

