<?php

	$dataProvider = $model->searchFileItems();
	$count = 0;
	foreach ($dataProvider->data as $fileItem) {
		// e.g. screenshot0.jpg, screenshot12.png
		if (!preg_match('/^screenshot[0-9]{1,3}\.(jpe?g|png)$/i', $fileItem->filename)) {
			continue;
		}
		$count++;
?>

<div class="img">
	<?php
	$fullUrl = $this->createAbsoluteUrl('viewScreenshot', array('name' => $fileItem->filename, 'rpt' => $model->id));
	$thumbUrl = $this->createAbsoluteUrl('viewScreenshotThumbnail', array('name' => $fileItem->filename, 'rpt' => $model->id));
	?>
	<a target="_blank" href="<?php echo CHtml::encode($fullUrl); ?>">
		<img src="<?php echo CHtml::encode($thumbUrl); ?>"
			onerror="this.onerror=null;this.src=<?php echo json_encode($fullUrl); ?>;"
			alt="<?php echo CHtml::encode($fileItem->filename); ?>" width="220" height="190" />
	</a>
	<div class="div.desc"><?php echo CHtml::link($fileItem->filename, $fullUrl); ?></div>
</div>

<?php
	}

	if ($count == 0) {
		echo '<i>There are no screenshots available.</i>';
	}
?>
