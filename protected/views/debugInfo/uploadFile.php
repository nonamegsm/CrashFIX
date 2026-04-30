<?php

$this->breadcrumbs=array(
	'Debug Info Files'=>array('debugInfo/index'),
	'Upload New File'
);

?>

<!-- Project Selection Form -->
<div class="span-26 last" id="div_proj_selection">	
	<?php echo CHtml::beginForm(array('site/setCurProject'), 'get', array('id'=>'proj_form')); ?>
	<div class="span-18 last">
		Current Project:
		<?php 		
			$models = Yii::app()->user->getMyProjects();
			$projects = CHtml::listData($models, 'id', 'name');			
			echo CHtml::dropDownList('proj', array('selected'=>Yii::app()->user->getCurProjectId()), $projects); 			
		?>					
		Version:
		<?php 		
			$selVer = -1;
			$versions = Yii::app()->user->getCurProjectVersions($selVer);			
			echo CHtml::dropDownList('ver', array('selected'=>$selVer), $versions); 
		?>		
	</div>
	<?php echo CHtml::endForm(); ?>		
</div>

<div class="span-18 prepend-top last">
<?php 
	if($submitted && !$model->hasErrors())
	{
		$fname = CHtml::encode($model->fileAttachment->getName());
		echo "<div class=\"flash-success\">";
		echo "<p><strong>File <em>$fname</em> uploaded successfully.</strong></p>";
		echo "<p style=\"font-style:italic; color:#555; margin:4px 0;\">Detected format: ";
		echo CHtml::encode($model->getFormatStr());
		echo "</p>";
		echo "<p style=\"margin:4px 0;\">Status: queued for processing.</p>";
		echo "<p>Upload another file?</p>";
		echo "</div>";
	}
		
?>
 
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'crash-group-form',
	'enableAjaxValidation'=>false,
	'htmlOptions'=>array('enctype'=>'multipart/form-data')
)); ?>

	<?php echo $form->errorSummary($model); ?>

	<?php echo $form->hiddenField($model, 'guid'); ?>
	
	<div class="row">
		<?php echo $form->label($model, 'fileAttachment'); ?>
		<?php echo $form->fileField($model,'fileAttachment'); ?>
		<?php echo $form->error($model, 'fileAttachment'); ?>
		<p class="hint" style="font-size: 11px; color: #666; margin-top: 4px;">
			Supported formats: PDB, DWARF in ELF (.so / .debug),
			DWARF in PE (.exe / .dll), stripped .debug companion files.
			Format detection runs server-side after upload.
		</p>
	</div>

	<div id="debug-info-upload-progress" style="display:none; margin: 12px 0; padding: 10px; border: 1px solid #ccc; background: #f8f8f8;">
		<div id="debug-info-upload-status" style="margin-bottom: 6px;">Preparing upload...</div>
		<div style="height: 14px; border: 1px solid #aaa; background: #fff;">
			<div id="debug-info-upload-bar" style="width: 0%; height: 14px; background: #6aa84f;"></div>
		</div>
		<div id="debug-info-upload-detail" style="font-size: 11px; color: #666; margin-top: 5px;">
			Large EXE/DLL debug files can take a while. Keep this page open until the upload completes.
		</div>
	</div>

	<div class="row buttons">
		<?php echo CHtml::submitButton('Upload'); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->

</div>

<?php
$script = <<<SCRIPT
$("#proj, #ver").bind("change", function(e)
{
	$("#proj_form").submit();
});

(function() {
	var form = document.getElementById("crash-group-form");
	if(!form)
		return;

	var progressBox = document.getElementById("debug-info-upload-progress");
	var progressBar = document.getElementById("debug-info-upload-bar");
	var statusText = document.getElementById("debug-info-upload-status");
	var detailText = document.getElementById("debug-info-upload-detail");
	var fileInput = document.getElementById("DebugInfo_fileAttachment");
	var submitButton = $(form).find(":submit").first();

	function formatBytes(bytes) {
		if(!bytes || bytes < 0)
			return "0 MB";
		return (bytes / 1048576).toFixed(1) + " MB";
	}

	function setProgress(percent, loaded, total) {
		progressBar.style.width = percent + "%";
		statusText.textContent = "Uploading... " + percent + "%";
		if(total > 0) {
			detailText.textContent = formatBytes(loaded) + " of " + formatBytes(total) +
				" sent. After upload, CrashFix will queue server-side format detection.";
		}
	}

	form.onsubmit = function(e) {
		var fileName = fileInput && fileInput.value ? fileInput.value.replace(/^.*[\\\\\\/]/, "") : "";
		if(!fileName)
			return true;

		progressBox.style.display = "block";
		progressBar.style.width = "0%";
		statusText.textContent = "Starting upload for " + fileName + "...";
		detailText.textContent = "Connecting to server. Keep this page open.";
		submitButton.prop("disabled", true).val("Uploading...");

		if(!window.FormData || !window.XMLHttpRequest) {
			statusText.textContent = "Uploading " + fileName + "...";
			detailText.textContent = "Your browser does not expose upload percentage, but the upload is in progress.";
			return true;
		}

		e.preventDefault();

		var xhr = new XMLHttpRequest();
		xhr.open(form.method || "POST", form.action || window.location.href, true);
		xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

		if(xhr.upload) {
			xhr.upload.onprogress = function(event) {
				if(event.lengthComputable) {
					var percent = Math.max(1, Math.min(99, Math.round((event.loaded / event.total) * 100)));
					setProgress(percent, event.loaded, event.total);
				} else {
					statusText.textContent = "Uploading " + fileName + "...";
				}
			};
		}

		xhr.onerror = function() {
			statusText.textContent = "Upload failed before the server responded.";
			detailText.textContent = "Check your network connection and try again.";
			submitButton.prop("disabled", false).val("Upload");
		};

		xhr.onload = function() {
			progressBar.style.width = "100%";
			statusText.textContent = "Upload complete. Loading server response...";
			detailText.textContent = "CrashFix is saving the file and queueing daemon processing.";
			document.open();
			document.write(xhr.responseText);
			document.close();
		};

		xhr.send(new FormData(form));
		return false;
	};
})();
SCRIPT;

Yii::app()->getClientScript()->registerScript(
	"debugInfoUploadProgress",
	$script,
	CClientScript::POS_READY);
?>