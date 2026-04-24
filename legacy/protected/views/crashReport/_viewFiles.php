<?php
/**
 * @var CrashReportController $this
 * @var CrashReport $model
 */
if (!function_exists('cf_crash_report_file_cell')) {
	function cf_crash_report_file_cell($name, $rpt)
	{
		$html = CHtml::link(CHtml::encode($name), array('crashReport/extractFile', 'name' => $name, 'rpt' => $rpt));
		$kind = CrashReport::previewUiKind($name);
		if ($kind !== null) {
			$url = $kind === 'text'
				? Yii::app()->createUrl('crashReport/previewFileText', array('name' => $name, 'rpt' => $rpt))
				: Yii::app()->createUrl('crashReport/inlineFile', array('name' => $name, 'rpt' => $rpt));
			$html .= ' '.CHtml::button('Preview', array(
				'type' => 'button',
				'class' => 'cf-file-preview-btn',
				'data-preview-type' => $kind,
				'data-preview-url' => $url,
				'data-preview-filename' => $name,
			));
		}
		return $html;
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
							echo cf_crash_report_file_cell($row['path'], $model->id);
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

<div class="quiet">Registered file items (database). You can download each file from the archive, or use Preview for text/images:</div>

<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$model->searchFileItems(),
	  'selectableRows'=>null,
      'columns'=>array(
		  array(            
              'name' => 'filename',
			  'type' => 'raw',
			  'value'=>'cf_crash_report_file_cell($data->filename, $data->crashreport_id)',
          ),
		  'description'
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

	$(document).on('click', '.cf-file-preview-btn', function () {
		var btn = $(this);
		var type = btn.attr('data-preview-type');
		var url = btn.attr('data-preview-url');
		var fname = btn.attr('data-preview-filename') || 'File';
		showOverlay();
		$title.text('Preview: ' + fname);

		if (type === 'image') {
			$img.off('error').on('error', function () {
				$imgW.hide();
				$err.show().text('Could not load image preview.');
			});
			$img.attr('src', url).attr('alt', fname);
			$imgW.show();
			return;
		}

		if (type === 'text') {
			$load.show();
			$.ajax({
				url: url,
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
			return;
		}

		$err.show().text('Unknown preview type.');
	});
})(jQuery);
JS;
Yii::app()->getClientScript()->registerScript('crashReportFilePreview', $previewJs, CClientScript::POS_READY);
?>
