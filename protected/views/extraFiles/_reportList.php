<!-- Grid view -->
<div class="span-27 last">
<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$dataProvider,
	  'selectableRows'=>null,
      'columns'=>array(
		  array(            
              'class'=>'CCheckBoxColumn',			  			  
			  'id'=>'DeleteRows',
			  'selectableRows'=>2, // allow multiple rows to select
          ),          
          array(            
              'name' => 'Name',
			  'type' => 'raw',
			  'value' => 'CHtml::link($data->name, array(\'extraFiles/view/\', \'id\'=>$data->id))',	  			  			 
			  'cssClassExpression' => '"column-right-align"',
          ),		  
		  array(                          			  
			  'name'=>'Date from',
			  'value'=>'date("d/m/y H:i", $data->date_from)',
          ),
		  array(                          			  
			  'name'=>'Date to',
			  'value'=>'date("d/m/y H:i", $data->date_to)',
          ),
		  array(                          			  
			  'name'=>'Status',
			  'value'=>'Lookup::item(\'CrashReportStatus\', $data->status)',
			  'cssClassExpression' => '$data->status==CrashReport::STATUS_INVALID?"status-invalid":""',		
          ),
          array(            
              'name'=>'Download',
			  'type' => 'raw',		  
			  'value' => '($data->path)?CHtml::link($data->name."_".$data->id.".zip", array(\'extraFiles/download/\', \'id\'=>$data->id)):null',	  			  			 
			  'cssClassExpression' => '"column-right-align"',
          ),          
      ),
 )); 
  
 ?>
 </div>
		
		
<?php 
 $script = <<<SCRIPT

$("#proj, #ver").bind('change', function(e)
{	
	$("#proj_form").submit();
});

$("#link_advanced_search").bind('click', function(e)
{	
	$("#div_simple_search").fadeToggle("fast");			
	$("#div_advanced_search").fadeToggle("fast");		
});
SCRIPT;
 
 Yii::app()->getClientScript()->registerScript(
		 "CrashReport", 
		 $script, 
		 CClientScript::POS_READY); 
 ?>
