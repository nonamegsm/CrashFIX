<?php
/** @var yii\web\View $this */
/** @var array<string,string>|string $licenseInfo */
/** @var array<string,string>        $configInfo */
/** @var string                      $webAppVer */

use yii\helpers\Html;

if (is_string($licenseInfo)) {
    // Backwards-compat with the original mock value.
    $licenseInfo = ['LicenseType' => $licenseInfo];
}
$configInfo = isset($configInfo) && is_array($configInfo) ? $configInfo : [];
$webAppVer  = isset($webAppVer)  ? (string) $webAppVer  : '';

$daemonVer  = (string) ($configInfo['DaemonVer']  ?? '');
$webRoot    = (string) ($configInfo['WebRootDir'] ?? '');
$pid        = (string) ($configInfo['ProcessId']  ?? '');
$uptime     = (string) ($configInfo['Uptime']     ?? '');
$daemonErr  = (string) ($configInfo['_error']     ?? '');

// Compare daemon binary version against the web-app version. A mismatch
// is not fatal but worth surfacing so admins notice when one side has
// been upgraded and the other has not.
$verMismatch = $daemonVer !== '' && $webAppVer !== '' && $daemonVer !== $webAppVer;
?>

<div id="license_info">

    <!-- Daemon Info -->
    <div class="card mb-3">
        <div class="card-body p-3">
            <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:0.05em; font-size:11px;">
                Daemon Info
            </h6>

            <?php if ($daemonErr !== ''): ?>
                <div class="alert alert-warning small mb-2">
                    <?= Html::encode($daemonErr) ?>
                </div>
            <?php endif; ?>

            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-muted">Daemon Version</dt>
                <dd class="col-sm-8">
                    <?php if ($daemonVer === ''): ?>
                        <span class="text-muted fst-italic">unavailable</span>
                    <?php else: ?>
                        <code><?= Html::encode($daemonVer) ?></code>
                        <?php if ($verMismatch): ?>
                            <span class="badge badge-warning ms-2"
                                  title="Web app reports v<?= Html::encode($webAppVer) ?>; daemon reports v<?= Html::encode($daemonVer) ?>. One side may need an upgrade.">
                                version mismatch
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </dd>

                <dt class="col-sm-4 text-muted">Web App Version</dt>
                <dd class="col-sm-8">
                    <?= $webAppVer === ''
                        ? '<span class="text-muted fst-italic">unknown</span>'
                        : '<code>' . Html::encode($webAppVer) . '</code>' ?>
                </dd>

                <?php if ($pid !== ''): ?>
                    <dt class="col-sm-4 text-muted">Process ID</dt>
                    <dd class="col-sm-8"><?= Html::encode($pid) ?></dd>
                <?php endif; ?>

                <?php if ($uptime !== ''): ?>
                    <dt class="col-sm-4 text-muted">Uptime</dt>
                    <dd class="col-sm-8"><?= Html::encode($uptime) ?></dd>
                <?php endif; ?>

                <?php if ($webRoot !== ''): ?>
                    <dt class="col-sm-4 text-muted">Web Root</dt>
                    <dd class="col-sm-8"><code><?= Html::encode($webRoot) ?></code></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- License Info -->
    <div class="card">
        <div class="card-body p-3">
            <h6 class="text-uppercase text-muted mb-2" style="letter-spacing:0.05em; font-size:11px;">
                License Info
            </h6>

            <?php if (!empty($licenseInfo['_error'])): ?>
                <div class="alert alert-warning small mb-3">
                    <?= Html::encode($licenseInfo['_error']) ?>
                </div>
            <?php endif; ?>

            <dl class="row mb-0 small">
                <?php foreach (['LicenseType', 'LicensedTo', 'DateCreated', 'ExpiresAt', 'MaxProjects', 'MaxUsers'] as $k):
                    $v = $licenseInfo[$k] ?? null;
                    if ($v === null || $v === '') continue;
                ?>
                    <dt class="col-sm-4 text-muted"><?= Html::encode($k) ?></dt>
                    <dd class="col-sm-8"><?= Html::encode((string) $v) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

</div>
