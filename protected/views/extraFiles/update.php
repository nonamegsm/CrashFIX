<?php
/* @var $this ExtraFilesController */
/* @var $model ExtraFiles */

$this->breadcrumbs=array(
	'Extra Files'=>array('index'),
	$model->name=>array('view','id'=>$model->id),
	'Update',
);

$this->menu=array(
	array('label'=>'List ExtraFiles', 'url'=>array('index')),
	array('label'=>'Create ExtraFiles', 'url'=>array('create')),
	array('label'=>'View ExtraFiles', 'url'=>array('view', 'id'=>$model->id)),
	array('label'=>'Manage ExtraFiles', 'url'=>array('admin')),
);
?>

<h1>Update ExtraFiles <?php echo $model->id; ?></h1>

<?php $this->renderPartial('_form', array('model'=>$model)); ?>