<?php

/** @var yii\web\View $this */
/** @var app\models\ResetPasswordForm $model */
/** @var yii\bootstrap5\ActiveForm $form */

use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;

$this->title = 'Reset Password';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="site-reset-password">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card shadow-sm mt-5">
                <div class="card-header bg-white">
                    <h1 class="h4 mb-0"><?= Html::encode($this->title) ?></h1>
                </div>
                <div class="card-body">
                    <p class="text-muted small">For security reasons, you must change your password on the first login.</p>

                    <?php $form = ActiveForm::begin(['id' => 'reset-password-form']); ?>

                        <?= $form->field($model, 'password')->passwordInput(['autofocus' => true]) ?>

                        <?= $form->field($model, 'password_repeat')->passwordInput() ?>

                        <div class="form-group d-grid mt-4">
                            <?= Html::submitButton('Change Password', ['class' => 'btn btn-primary', 'name' => 'reset-button']) ?>
                        </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
