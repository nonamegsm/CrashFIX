<?php

$this->breadcrumbs=array(
	'Debug Info Files'=>array('debugInfo/index'),
	'Upload New File'
);

?>

<!-- Project Selection Form -->
<div class="span-26 last" id="div_proj_selection">	
	<?php echo CHtml::beginForm(array('site/setCurProject'), 'get', array('id'=>'proj_form')); ?>
	<div class="span-18 last">
		Current Project:
		<?php 		
			$models = Yii::app()->user->getMyProjects();
			$projects = CHtml::listData($models, 'id', 'name');			
			echo CHtml::dropDownList('proj', array('selected'=>Yii::app()->user->getCurProjectId()), $projects); 			
		?>					
		Version:
		<?php 		
			$selVer = -1;
			$versions = Yii::app()->user->getCurProjectVersions($selVer);			
			echo CHtml::dropDownList('ver', array('selected'=>$selVer), $versions); 
		?>		
	</div>
	<?php echo CHtml::endForm(); ?>		
</div>

<div class="span-18 prepend-top last">
<?php 
	if($submitted && !$model->hasErrors())
	{
		$fname = CHtml::encode($model->fileAttachment->getName());
		echo "<div class=\"flash-success\">";
		echo "<p><strong>File <em>$fname</em> uploaded successfully.</strong></p>";
		echo "<p style=\"font-style:italic; color:#555; margin:4px 0;\">Detected format: ";
		echo CHtml::encode($model->getFormatStr());
		echo "</p>";
		echo "<p style=\"margin:4px 0;\">Status: queued for processing.</p>";
		echo "<p>Upload another file?</p>";
		echo "</div>";
	}
		
?>
 
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'crash-group-form',
	'enableAjaxValidation'=>false,
	'htmlOptions'=>array('enctype'=>'multipart/form-data')
)); ?>

	<?php echo $form->errorSummary($model); ?>

	<?php echo $form->hiddenField($model, 'guid'); ?>
	
	<div class="row">
		<?php echo $form->label($model, 'fileAttachment'); ?>
		<?php echo $form->fileField($model,'fileAttachment'); ?>
		<?php echo $form->error($model, 'fileAttachment'); ?>
		<p class="hint" style="font-size: 11px; color: #666; margin-top: 4px;">
			Supported formats: PDB, DWARF in ELF (.so / .debug),
			DWARF in PE (.exe / .dll), stripped .debug companion files.
			Format detection runs server-side after upload.
		</p>
	</div>

	<div class="row buttons">
		<?php echo CHtml::submitButton('Upload'); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->

</div>