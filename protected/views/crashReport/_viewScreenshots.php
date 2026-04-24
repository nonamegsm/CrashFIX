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
	<a class="cf-ss-open" href="<?php echo CHtml::encode($fullUrl); ?>" data-full="<?php echo CHtml::encode($fullUrl); ?>" data-name="<?php echo CHtml::encode($fileItem->filename); ?>">
		<img src="<?php echo CHtml::encode($thumbUrl); ?>"
			onerror="this.onerror=null;this.src=<?php echo json_encode($fullUrl); ?>;"
			alt="<?php echo CHtml::encode($fileItem->filename); ?>" width="220" height="190" />
	</a>
	<div class="div.desc"><?php echo CHtml::link($fileItem->filename, $fullUrl, array('class' => 'cf-ss-open', 'data-full' => $fullUrl, 'data-name' => $fileItem->filename)); ?></div>
</div>

<?php
	}

	if ($count == 0) {
		echo '<i>There are no screenshots available.</i>';
	}
?>

<div id="cf-ss-overlay" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.75);z-index:2000;">
	<div id="cf-ss-modal" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;max-width:92vw;max-height:92vh;padding:10px;border-radius:4px;box-shadow:0 8px 24px rgba(0,0,0,.4);">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
			<strong id="cf-ss-title" style="font-size:13px;color:#444;">Screenshot</strong>
			<a href="#" id="cf-ss-close" style="text-decoration:none;font-size:22px;line-height:1;">&times;</a>
		</div>
		<div style="text-align:center;">
			<img id="cf-ss-image" src="" alt="" style="max-width:88vw;max-height:82vh;display:block;" />
		</div>
	</div>
</div>

<?php
$script = <<<JS
(function($){
	function closeShotModal() {
		$('#cf-ss-overlay').hide();
		$('#cf-ss-image').attr('src', '');
	}
	$(document).off('click.cfShotOpen').on('click.cfShotOpen', 'a.cf-ss-open', function(e){
		e.preventDefault();
		var full = $(this).attr('data-full') || $(this).attr('href');
		var name = $(this).attr('data-name') || 'Screenshot';
		$('#cf-ss-title').text(name);
		$('#cf-ss-image').attr('alt', name).attr('src', full);
		$('#cf-ss-overlay').show();
	});
	$(document).off('click.cfShotClose').on('click.cfShotClose', '#cf-ss-close,#cf-ss-overlay', function(e){
		if (e.target.id === 'cf-ss-overlay' || e.target.id === 'cf-ss-close') {
			e.preventDefault();
			closeShotModal();
		}
	});
	$(document).off('keydown.cfShotEsc').on('keydown.cfShotEsc', function(e){
		if (e.keyCode === 27) {
			closeShotModal();
		}
	});
})(jQuery);
JS;
Yii::app()->getClientScript()->registerScript('CrashReportScreenshotsModal', $script, CClientScript::POS_READY);
?>
