<?php
/* @var $this ExtraFilesController */
/* @var $model ExtraFiles */

$this->breadcrumbs=array(
	'Extra Files'=>array('index'),
	'Create',
);

$this->menu=array(
	array('label'=>'List ExtraFiles', 'url'=>array('index')),
	array('label'=>'Manage ExtraFiles', 'url'=>array('admin')),
);
?>

<h1>Create ExtraFiles</h1>

<?php $this->renderPartial('_form', array('model'=>$model)); ?>