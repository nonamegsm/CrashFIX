<?php
/* @var $this SerialsInfoController */
/* @var $model SerialsInfo */
/* @var $form CActiveForm */

$this->breadcrumbs=array(
    'Serials Info',
);

$this->menu=array(
    array('label'=>'Manage Serials Info', 'url'=>array('index')),
);
?>

<h1>Serials Info</h1>

<?php $this->widget('zii.widgets.grid.CGridView', array(
    'id' => 'serial-report-count-grid',
    'dataProvider' => $model->search(),
    'filter' => $model,
    'columns' => array(
        'box_serial',
        'card_serial',
        'report_count',
        array(
            'class' => 'CButtonColumn',
        ),
    ),
)); ?>
