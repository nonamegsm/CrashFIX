<?php
/* @var $licenseInfo array|false */
/* @var $configInfo  array|false */
/* @var $webAppVer   string */

$configInfo = isset($configInfo) && is_array($configInfo) ? $configInfo : array();
$webAppVer  = isset($webAppVer)  ? (string)$webAppVer  : '';

$daemonVer  = isset($configInfo['DaemonVer'])  ? (string)$configInfo['DaemonVer']  : '';
$webRoot    = isset($configInfo['WebRootDir']) ? (string)$configInfo['WebRootDir'] : '';

// Mark mismatch but don't fail - useful when admin upgrades daemon or
// web app independently and forgets the other side.
$verMismatch = $daemonVer !== '' && $webAppVer !== '' && $daemonVer !== $webAppVer;
?>

<div class="div_license">

<!-- Daemon Info -->
<h5>Daemon Info:</h5>
<ul class="daemon-status-list">
	<li class="daemon-status-list-item">
		<span class="list-item-label">Daemon Version:</span>
		<?php if ($daemonVer === ''): ?>
			<i style="color:#888;">unavailable</i>
		<?php else: ?>
			<code><?php echo CHtml::encode($daemonVer); ?></code>
			<?php if ($verMismatch): ?>
				<span style="display:inline-block; margin-left:8px; padding:1px 6px;
				             background:#fff3cd; color:#856404; border-radius:3px;
				             font-size:11px;"
				      title="Web app reports v<?php echo CHtml::encode($webAppVer); ?>; daemon reports v<?php echo CHtml::encode($daemonVer); ?>. One side may need an upgrade.">
					version mismatch
				</span>
			<?php endif; ?>
		<?php endif; ?>
	</li>
	<li class="daemon-status-list-item">
		<span class="list-item-label">Web App Version:</span>
		<?php echo $webAppVer === ''
			? '<i style="color:#888;">unknown</i>'
			: '<code>' . CHtml::encode($webAppVer) . '</code>'; ?>
	</li>
	<?php if ($webRoot !== ''): ?>
		<li class="daemon-status-list-item">
			<span class="list-item-label">Web Root:</span>
			<code><?php echo CHtml::encode($webRoot); ?></code>
		</li>
	<?php endif; ?>
</ul>

<?php if($licenseInfo!=False): ?>

<h5 class="prepend-top">Product Info:</h5>

<ul class="daemon-status-list">	
	<li class="daemon-status-list-item"><span class="list-item-label">Product Name:</span>CrashFix</li>
	<li class="daemon-status-list-item"><span class="list-item-label">Product Version:</span> <?php echo CHtml::encode($licenseInfo['ProductVersion'])?></li>
	<li class="daemon-status-list-item">
		<span class="list-item-label">Edition:</span> 
		<?php 
			echo CHtml::encode($licenseInfo['Edition']);
			if($licenseInfo['Evaluation']==1)
			{
				echo ' (Evaluation version)';
			}
		?>
	</li>	
	<li class="daemon-status-list-item"><span class="list-item-label">Date Created:</span> <?php echo CHtml::encode($licenseInfo['DateCreated'])?></li>	
	<?php if($licenseInfo['Evaluation']==1): ?>
		<li class="daemon-status-list-item">
			<span class="list-item-label">Expires in:</span> 
			<?php echo CHtml::encode($licenseInfo['DaysExpiresFromNow'])?> day(s)			
		</li>	
	<?php endif; ?>
</ul>		
	
<h5 class="prepend-top">Customer Info:</h5>	

<ul class="">	
	<li class="daemon-status-list-item"><span class="list-item-label">Name:</span>&nbsp;<?php echo CHtml::encode($licenseInfo['Name'])?></li>
	<li class="daemon-status-list-item"><span class="list-item-label">Surname:</span>&nbsp;<?php echo CHtml::encode($licenseInfo['Surname'])?></li>	
</ul>		

<?php else: ?>

<div class="error">
<i>Error retrieving license information, because the daemon is inactive or not responding.</i>
</div>

<?php endif; ?>

</div>
