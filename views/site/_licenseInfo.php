<?php
/** @var yii\web\View $this */
/** @var array<string,string>|string $licenseInfo */

use yii\helpers\Html;

if (is_string($licenseInfo)) {
    // Backwards-compat with the original mock value.
    $licenseInfo = ['LicenseType' => $licenseInfo];
}
?>

<div id="license_info" class="card">
    <div class="card-body p-3">
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
