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
                <strong>Existing Yii1:</strong> reuse the same MySQL database and the live
                <code>protected/data</code> directory.
                <strong>New installation:</strong> use an empty database — optionally set the data path
                if you already copied that folder (crash reports / symbols) from a backup.
            </p>

            <?php $form = ActiveForm::begin(); ?>

            <?= $form->field($model, 'install_profile')->radioList(
                [
                    'fresh' => '<strong>New installation</strong> — empty or new database; files usually under this app’s <code>data/</code> unless you attach an existing data directory below.',
                    'existing_yii1' => '<strong>Existing CrashFix (Yii1)</strong> — connect to the database you already run in production and use the live legacy data path (migrations run in &ldquo;adopt&rdquo; mode).',
                ],
                ['encode' => false, 'separator' => '<div class="mb-2"></div>']
            )->label('Setup type'); ?>

            <div id="legacy-path-block" class="border rounded p-3 mb-3 bg-light" style="display: none;">
                <p id="legacy-path-hint-required" class="small text-muted mb-2" style="display: none;">
                    <strong>Required.</strong> Absolute path to the <strong>Yii1</strong>
                    <code>protected/data</code> directory (contains <code>crashReports</code>,
                    <code>debugInfo</code>, etc.).
                </p>
                <p id="legacy-path-hint-optional" class="small text-muted mb-2" style="display: none;">
                    <strong>Optional.</strong> Only if you copied the Yii1 <code>protected/data</code>
                    tree (e.g. from a backup) and want this install to read those crash dumps —
                    leave blank to use the default <code>data/</code> layout instead.
                </p>
                <?= $form->field($model, 'legacy_data_path')->textInput([
                    'placeholder' => 'e.g. C:\\inetpub\\crashfix\\protected\\data   or   /var/www/crashfix/protected/data',
                    'class' => 'form-control font-monospace',
                ])->label('Legacy / imported data directory'); ?>
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
    var hintReq = document.getElementById('legacy-path-hint-required');
    var hintOpt = document.getElementById('legacy-path-hint-optional');
    if (block) {
        block.style.display = 'block';
    }
    if (hintReq && hintOpt) {
        if (v === 'existing_yii1') {
            hintReq.style.display = 'block';
            hintOpt.style.display = 'none';
        } else {
            hintReq.style.display = 'none';
            hintOpt.style.display = 'block';
        }
    }
};
window.installToggleLegacy();
$(document).on('change', 'input[name="DynamicModel[install_profile]"]', window.installToggleLegacy);
JS
);
