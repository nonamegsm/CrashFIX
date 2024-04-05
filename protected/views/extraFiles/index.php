<?php
$this->pageTitle=Yii::app()->name . ' - Browse Extra Files';
$this->breadcrumbs=array(	
	'Extra Files',
);

?>


<?php if(count(Yii::app()->user->getMyProjects())==0): ?>

You have no projects assigned.

<?php else: ?>

<!-- Project Selection Form -->
<div class="span-27" id="div_proj_selection">	
	<?php echo CHtml::beginForm(array('site/setCurProject'), 'get', array('id'=>'proj_form')); ?>
	<div class="span-18">
		Current Project:
		<?php 		
			$models = Yii::app()->user->getMyProjects();
			$projects = CHtml::listData($models, 'id', 'name');			
			echo CHtml::dropDownList('proj', array('selected'=>Yii::app()->user->getCurProjectId()), $projects); 			
		?>			
		<?php 		
			$selVer = -1;	
			echo CHtml::textField('ver', $selVer, array('style'=>'display:none;')); 
		?>	
	</div>
	<?php echo CHtml::endForm(); ?>		
</div>

<!-- Project Selection Form -->
<div class="span-27 last" id="div_date_selection">	
	<p id="stat_filter">Create new extra files collection:</p>
	<?php echo CHtml::beginForm(array('extraFiles/create'), 'get', array('id'=>'date_form')); ?>
	<div class="span-18">
		From:
		<?php 
			$this->widget('zii.widgets.jui.CJuiDatePicker', array(
				'name'=>'date_from',
				'value'=>$model->date_from,
				// additional javascript options for the date picker plugin
				'options'=>array(
					'showAnim'=>'fold',
					'dateFormat'=>'dd/mm/yy',
				),
				'htmlOptions'=>array(
					//'style'=>'height:20px;'
				),
			  ));
		?>
		To:
		<?php 
			$this->widget('zii.widgets.jui.CJuiDatePicker', array(
				'name'=>'date_to',
				'value'=>$model->date_to,
				// additional javascript options for the date picker plugin
				'options'=>array(
					'showAnim'=>'fold',
					'dateFormat'=>'dd/mm/yy',
				),
				'htmlOptions'=>array(
					//'style'=>'height:20px;'
				),
			  ));
		?>	
	</div>
	<div class="span-18">
	<?php echo CHtml::submitButton('Create'); ?>
	</div>
		
	<?php echo CHtml::endForm(); ?>		
</div>

<?php $this->renderPartial('_reportList', 
				array(
					'route'=>array('extraFiles/index'),
					'model'=>$model,
					'dataProvider'=>$dataProvider,
				)
			); 
?>

<div class="span-27 last footnote">
	<?php 
		$curVer = "";
		$totalFileSize = 0;
		$percentOfQuota = 0;		
		$count = Yii::app()->user->getCurProject()->getCrashReportCount(
						$totalFileSize, $percentOfQuota);		
		$totalFileSizeStr = MiscHelpers::fileSizeToStr($totalFileSize);
		$percentOfQuotaStr = sprintf("%.0f", $percentOfQuota);
		echo "This project contains total $totalFileSizeStr in $count file(s)";
        if($percentOfQuota>=0) 
            echo " ($percentOfQuotaStr% of disc quota).";
	?> 	
</div>

<?php endif; ?>