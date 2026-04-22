<?php
/** @var yii\web\View $this */
/** @var yii\base\DynamicModel $model */
use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

$this->title = 'Database Configuration - CrashFix Setup';
?>

<div class="install-db-config my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">Database Configuration</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-4">Provide your MySQL database connection details. The setup will create a configuration file in <code>config/user_params.ini</code>.</p>
            
            <?php $form = ActiveForm::begin(); ?>

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
