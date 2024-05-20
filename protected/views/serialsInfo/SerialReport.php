<?php

<?php
/* @var $this SerialsInfoController */
/* @var $model SerialsInfo */

$this->breadcrumbs=array(
    'Serials Info',
);

$this->menu=array(
    array('label'=>'Manage Serials Info', 'url'=>array('index')),
);

?>

<h1>Serial Report</h1>

<?php $this->widget('zii.widgets.grid.CGridView', array(
    'id'=>'serials-info-grid',
    'dataProvider'=>$model->search(),
    'filter'=>$model,
    'columns'=>array(
        'box_serial',
        'card_serial',
        'report_count',
        array(
            'class'=>'CButtonColumn',
        ),
    ),
)); ?>
