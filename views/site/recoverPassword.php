<?php
/** @var yii\web\View $this */
/** @var app\models\RecoverPasswordForm $model */

use yii\bootstrap4\ActiveForm;
use yii\captcha\Captcha;
use yii\helpers\Html;

$this->title = Yii::$app->name . ' - Recover Password';
?>

<div class="recover-password mx-auto" style="max-width: 480px;">
    <div class="card shadow-sm mt-5">
        <div class="card-header bg-white">
            <h4 class="mb-0">Recover Lost Password</h4>
        </div>
        <div class="card-body p-4">

            <?php if (Yii::$app->session->hasFlash('recoverPassword')): ?>
                <div class="alert alert-success">
                    <?= Html::encode(Yii::$app->session->getFlash('recoverPassword')) ?>
                </div>
            <?php else: ?>

                <p class="text-muted">Enter your account user name and the e-mail address registered to it. We will send you a one-time link you can follow to set a new password.</p>

                <?php $form = ActiveForm::begin([
                    'id' => 'recover-password-form',
                    'fieldConfig' => [
                        'template' => "{label}\n{input}\n{hint}\n{error}",
                        'labelOptions' => ['class' => 'form-label'],
                        'inputOptions' => ['class' => 'form-control'],
                        'errorOptions' => ['class' => 'invalid-feedback d-block'],
                    ],
                ]); ?>

                <?= $form->errorSummary($model) ?>

                <p class="small text-muted">Fields with <span class="text-danger">*</span> are required.</p>

                <?= $form->field($model, 'username')->textInput(['autofocus' => true, 'maxlength' => true]) ?>
                <?= $form->field($model, 'email')->textInput(['type' => 'email']) ?>

                <?php if (Captcha::checkRequirements()): ?>
                    <?= $form->field($model, 'verifyCode')->widget(Captcha::class, [
                        'captchaAction' => 'site/captcha',
                        'template' => '<div class="row"><div class="col-5">{image}</div><div class="col-7">{input}</div></div>',
                        'imageOptions' => ['class' => 'img-fluid border'],
                    ])->hint('Enter the letters as shown in the image. Letters are not case-sensitive.') ?>
                <?php endif; ?>

                <div class="d-flex justify-content-between mt-4">
                    <?= Html::a('Back to Login', ['site/login'], ['class' => 'btn btn-link']) ?>
                    <?= Html::submitButton('Send Recovery E-mail', ['class' => 'btn btn-primary px-4']) ?>
                </div>

                <?php ActiveForm::end(); ?>

            <?php endif; ?>

        </div>
    </div>
</div>
