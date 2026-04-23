<?php
/** @var yii\web\View $this */
/** @var yii\base\DynamicModel $model */

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

$this->title = 'Database & storage - CrashFix Setup';
?>

<div class="install-db-config my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 720px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">Database and file storage</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-3">
                Connection details are saved to <code>config/user_params.ini</code>.
                For an existing Yii1 CrashFix server you can reuse the <strong>same database</strong>
                and point this app at the old <code>protected/data</code> tree so crash dumps and
                symbols stay in one place — no manual file copy.
            </p>

            <?php $form = ActiveForm::begin(); ?>

            <?= $form->field($model, 'install_profile')->radioList(
                [
                    'fresh' => '<strong>New installation</strong> — empty or new database; files stored under this app’s <code>data/</code> directory.',
                    'existing_yii1' => '<strong>Existing CrashFix (Yii1)</strong> — connect to the production database you already have and use the legacy on-disk layout.',
                ],
                ['encode' => false, 'separator' => '<div class="mb-2"></div>']
            )->label('Setup type'); ?>

            <div id="legacy-path-block" class="border rounded p-3 mb-3 bg-light" style="display: none;">
                <p class="small text-muted mb-2">
                    Absolute path to the <strong>Yii1</strong> directory that contains
                    <code>crashReports</code>, <code>debugInfo</code>, etc. — usually
                    <code>…/protected/data</code> on the old server.
                </p>
                <?= $form->field($model, 'legacy_data_path')->textInput([
                    'placeholder' => 'e.g. C:\\inetpub\\crashfix\\protected\\data   or   /var/www/crashfix/protected/data',
                    'class' => 'form-control font-monospace',
                ])->label('Legacy data directory'); ?>
            </div>

            <hr class="my-4">

            <?= $form->field($model, 'host')->textInput(['placeholder' => '127.0.0.1']) ?>
            <?= $form->field($model, 'dbname')->textInput(['placeholder' => 'crashfix']) ?>
            <?= $form->field($model, 'username')->textInput(['placeholder' => 'root']) ?>
            <?= $form->field($model, 'password')->passwordInput() ?>
            <?= $form->field($model, 'tablePrefix')->textInput(['placeholder' => 'tbl_']) ?>

            <div class="d-flex justify-content-between mt-5">
                <?= Html::a('Back', ['requirements'], ['class' => 'btn btn-outline-secondary']) ?>
                <?= Html::submitButton('Save & Continue', ['class' => 'btn btn-primary px-4']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
window.installToggleLegacy = function () {
    var sel = document.querySelector('input[name="DynamicModel[install_profile]"]:checked');
    var v = sel ? sel.value : 'fresh';
    var block = document.getElementById('legacy-path-block');
    if (block) block.style.display = (v === 'existing_yii1') ? 'block' : 'none';
};
window.installToggleLegacy();
$(document).on('change', 'input[name="DynamicModel[install_profile]"]', window.installToggleLegacy);
JS
);
