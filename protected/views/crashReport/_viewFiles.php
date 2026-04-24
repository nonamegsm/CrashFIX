<?php
/**
 * @var CrashReportController $this
 * @var CrashReport $model
 */
if (!function_exists('cf_crash_report_file_name_text')) {
	/** @return string Plain file name (no link; download is a separate action). */
	function cf_crash_report_file_name_text($name)
	{
		return CHtml::encode($name);
	}
}

if (!function_exists('cf_crash_report_file_actions')) {
	/**
	 * Download (always) + View (in-page preview when supported: text, images).
	 * @return string
	 */
	function cf_crash_report_file_actions($name, $rpt)
	{
		$dlUrl = array('crashReport/extractFile', 'name' => $name, 'rpt' => $rpt);
		$dl = CHtml::link('Download', $dlUrl, array('class' => 'cf-file-download', 'title' => 'Download this file from the archive'));
		$kind = CrashReport::previewUiKind($name);
		if ($kind === null) {
			$view = ' <span class="cf-file-view-na" style="color:#999;margin-left:8px" title="No in-browser preview for this file type. Use Download.">View</span>';
			return '<span class="cf-file-actions">'.$dl.$view.'</span>';
		}
		$previewUrl = $kind === 'text'
			? Yii::app()->createUrl('crashReport/previewFileText', array('name' => $name, 'rpt' => $rpt))
			: Yii::app()->createUrl('crashReport/inlineFile', array('name' => $name, 'rpt' => $rpt));
		$view = CHtml::htmlButton('View', array(
			'type' => 'button',
			'class' => 'cf-file-preview-launch',
			'title' => 'View in this page (text or image only)',
			'data-preview-type' => $kind,
			'data-preview-url' => $previewUrl,
			'data-preview-filename' => $name,
		));
		return '<span class="cf-file-actions">'.$dl.' '.$view.'</span>';
	}
}
?>

<style type="text/css">
#cf-file-preview-overlay { display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,.45); z-index:10000; overflow:auto; }
#cf-file-preview-inner { margin:24px auto; max-width:920px; background:#fff; border:1px solid #999; padding:12px 40px 12px 12px; position:relative; }
#cf-file-preview-close { position:absolute; right:10px; top:8px; cursor:pointer; font-weight:bold; font-size:18px; color:#333; text-decoration:none; }
#cf-file-preview-title { margin:0 0 8px 0; font-size:15px; }
#cf-file-preview-meta { font-size:12px; color:#666; margin-bottom:8px; }
#cf-file-preview-loading { color:#666; }
#cf-file-preview-error { color:#a00; margin-bottom:8px; }
#cf-file-preview-pre { max-height:70vh; overflow:auto; white-space:pre-wrap; word-break:break-word; font-size:13px; background:#f5f5f5; border:1px solid #ddd; padding:8px; margin:0; }
#cf-file-preview-image { max-width:100%; max-height:75vh; }
span.cf-file-actions a.cf-file-download { font-weight: normal; }
span.cf-file-actions .cf-file-preview-launch { margin-left: 6px; }
</style>

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

<div class="quiet" style="margin-bottom:8px">Files contained in this crash report archive (from database; same members as the ZIP):</div>

<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$model->searchFileItems(),
	  'selectableRows'=>null,
      'columns'=>array(
		  array(
              'name' => 'filename',
			  'type' => 'raw',
			  'value'=>'cf_crash_report_file_name_text($data->filename)',
          ),
		  'description',
		  array(
			  'header' => 'Download / View',
			  'type' => 'raw',
			  'value' => 'cf_crash_report_file_actions($data->filename, $data->crashreport_id)',
		  ),
      ),
  )); ?>

</div>

<div id="cf-file-preview-overlay">
	<div id="cf-file-preview-inner">
		<a href="#" id="cf-file-preview-close" title="Close">&times;</a>
		<h3 id="cf-file-preview-title">Preview</h3>
		<div id="cf-file-preview-loading" style="display:none;">Loading…</div>
		<div id="cf-file-preview-error" style="display:none;"></div>
		<div id="cf-file-preview-meta" style="display:none;"></div>
		<pre id="cf-file-preview-pre" style="display:none;"></pre>
		<div id="cf-file-preview-image-wrap" style="display:none; text-align:center;">
			<img id="cf-file-preview-image" src="" alt="" />
		</div>
	</div>
</div>

<?php
Yii::app()->getClientScript()->registerCoreScript('jquery');
$previewJs = <<<'JS'
(function ($) {
	var $ov = $('#cf-file-preview-overlay');
	var $load = $('#cf-file-preview-loading');
	var $err = $('#cf-file-preview-error');
	var $meta = $('#cf-file-preview-meta');
	var $pre = $('#cf-file-preview-pre');
	var $imgW = $('#cf-file-preview-image-wrap');
	var $img = $('#cf-file-preview-image');
	var $title = $('#cf-file-preview-title');

	function reset() {
		$load.hide();
		$err.hide().text('');
		$meta.hide().text('');
		$pre.hide().text('');
		$imgW.hide();
		$img.removeAttr('src').removeAttr('alt');
	}

	function showOverlay() {
		reset();
		$ov.show();
	}

	$('#cf-file-preview-close').on('click', function (e) {
		e.preventDefault();
		$ov.hide();
		reset();
	});
	$ov.on('click', function (e) {
		if (e.target === this) {
			$ov.hide();
			reset();
		}
	});

	$(document).on('click', '.cf-file-preview-launch', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var btn = $(this);
		var type = String(btn.attr('data-preview-type') || '');
		var url = String(btn.attr('data-preview-url') || '');
		var fname = String(btn.attr('data-preview-filename') || 'File');
		if (!url || (type !== 'text' && type !== 'image')) {
			reset();
			$ov.show();
			$title.text('Preview: ' + fname);
			$err.show().text('Preview is not available. Please check that the page finished loading and try again.');
			return false;
		}
		showOverlay();
		$title.text('Preview: ' + fname);

		if (type === 'image') {
			$img.off('error').on('error', function () {
				$imgW.hide();
				$err.show().text('Could not load image preview.');
			});
			$img.attr('src', url).attr('alt', fname);
			$imgW.show();
			return false;
		}

		if (type === 'text') {
			$load.show();
			$.ajax({
				url: url,
				cache: false,
				dataType: 'json',
				success: function (data) {
					$load.hide();
					if (!data || !data.ok) {
						$err.show().text('Preview failed.');
						return;
					}
					var meta = 'Size on disk: ' + (data.size != null ? data.size + ' bytes' : 'unknown');
					if (data.truncated) {
						meta += ' — showing first ' + data.maxBytes + ' bytes only';
					}
					$meta.show().text(meta);
					$pre.show().text(data.content != null ? data.content : '');
				},
				error: function (xhr) {
					$load.hide();
					var msg = 'Could not load preview.';
					if (xhr.responseText) {
						msg = xhr.responseText.substring(0, 300);
					}
					$err.show().text(msg);
				}
			});
			return false;
		}

		$err.show().text('Unknown preview type.');
		return false;
	});
})(jQuery);
JS;
Yii::app()->getClientScript()->registerScript('crashReportFilePreview', $previewJs, CClientScript::POS_READY);
?>
