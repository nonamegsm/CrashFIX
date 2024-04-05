<?php
/* @var $this ExtraFilesController */
/* @var $model ExtraFiles */

$this->breadcrumbs=array(
	'Extra Files'=>array('index'),
	$model->name,
);

$this->menu=array(
	array('label'=>'List ExtraFiles', 'url'=>array('index')),
	array('label'=>'Create ExtraFiles', 'url'=>array('create')),
	array('label'=>'Update ExtraFiles', 'url'=>array('update', 'id'=>$model->id)),
	array('label'=>'Delete ExtraFiles', 'url'=>'#', 'linkOptions'=>array('submit'=>array('delete','id'=>$model->id),'confirm'=>'Are you sure you want to delete this item?')),
	array('label'=>'Manage ExtraFiles', 'url'=>array('admin')),
);
?>

<h1>View ExtraFiles #<?php echo $model->id; ?></h1>

<?php $this->widget('zii.widgets.CDetailView', array(
	'data'=>$model,
	'attributes'=>array(
		//'id',
		//'project_id',
		'name',	  
		  array(                          			  
			  'name'=>'Date from',
			  'value'=>date("d/m/y H:i", $model->date_from),
          ),
		  array(                          			  
			  'name'=>'Date to',
			  'value'=>date("d/m/y H:i", $model->date_to),
          ),
		  array(                          			  
			  'name'=>'status',
			  'value'=>Lookup::item('CrashReportStatus', $model->status),
			  'cssClassExpression' => $model->status==CrashReport::STATUS_INVALID?"status-invalid":"",		
          ),
          array(            
              'name'=>'Download',
			  'type' => 'raw',		  
			  'value' => ($model->path)?CHtml::link($model->name."_".$model->id.".zip", array('extraFiles/download/', 'id'=>$model->id)):null,	  			  			 
			  'cssClassExpression' => '"column-right-align"',
          ),         
	),
)); ?>

<div class="span-27" id="div_date_selection">	

	<div class="span-18 last">
	<?php echo CHtml::button("Delete", 
			array(
				'submit'=>$this->createUrl('extraFiles/delete', array('id'=>$model->id)),
				'form'=>'del_form',
				'confirm'=>"Are you sure you want to permanently delete this extra files collection?"
			)); 
	?>
	
	<?php if ($model->status != CrashReport::STATUS_PROCESSING_IN_PROGRESS)
		echo CHtml::button("Process", 
			array(
				'submit'=>$this->createUrl('extraFiles/process', array('id'=>$model->id)),
				'form'=>'del_form',
				'confirm'=>"Are you sure you want to process this extra files collection?"
			)); 
	?>
	
	</div>
</div>

<!-- Grid view -->
<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$model->searchExtraFilesItems(),
	  'selectableRows'=>null,
      'columns'=>array(
		  array(            
              'name' => 'filename',
			  'type' => 'raw',
			  'value' => 'CHtml::link($data->filename, array(\'crashReport/extractFile\', \'name\'=>$data->filename, \'rpt\'=>$data->crashreport_id))',	  			  			  
          ),
		  'description'
      ),
 )); 
  
 ?>
