
<div class="span-18 last">
	<div class="box">
		<ul class="daemon-status-list">			
			<li class="daemon-status-list-item loud"><div class="list-item-label">File Name:</div><?php echo CHtml::encode($model->srcfilename); ?></li>
			<li class="daemon-status-list-item loud"><div class="list-item-label">File Size:</div><?php echo CHtml::encode(MiscHelpers::fileSizeToStr($model->filesize));?></li>
			<li class="daemon-status-list-item loud"><div class="list-item-label">MD5 Hash:</div><?php echo CHtml::encode($model->md5);?></li>
			<li class="daemon-status-list-item"><?php echo CHtml::link('Download Entire ZIP Archive', array('crashReport/download', 'id'=>$model->id)); ?></li>			
		</ul>
	</div>
</div>

<div class="span-18 last">
	<div class="box">
		<div class="list-item-label loud" style="margin-bottom:6px;">ZIP archive index</div>
		<div class="quiet" style="margin-bottom:8px;">Member paths and uncompressed sizes from the ZIP central directory (no file extraction).</div>
		<?php
		$zipRows = $model->listZipCentralDirectoryMembers();
		$zipPath = $model->getLocalFilePath();
		if ($zipPath === false || !is_file($zipPath)) {
			echo '<div class="quiet"><i>Archive file is not on disk.</i></div>';
		} elseif (count($zipRows) === 0) {
			echo '<div class="quiet"><i>No entries could be read (empty or unreadable archive).</i></div>';
		} else {
		?>
		<table class="items">
			<thead>
				<tr>
					<th>Member path</th>
					<th>Uncompressed size</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($zipRows as $row): ?>
				<tr>
					<td>
						<?php
						if (!empty($row['is_dir'])) {
							echo CHtml::encode($row['path']);
						} else {
							echo CHtml::link(CHtml::encode($row['path']), array('crashReport/extractFile', 'name' => $row['path'], 'rpt' => $model->id));
						}
						?>
					</td>
					<td><?php echo !empty($row['is_dir']) ? '—' : CHtml::encode(MiscHelpers::fileSizeToStr($row['size'])); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php } ?>
	</div>
</div>

<div class="span-18 last">

<div class="quiet">Registered file items (database). You can download each file from the archive:</div>

<!-- Grid view -->
<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$model->searchFileItems(),
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

</div>