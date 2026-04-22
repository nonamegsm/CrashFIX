<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap4\ActiveForm $form */
/** @var app\models\LoginForm $model */

use yii\bootstrap4\ActiveForm;
use yii\bootstrap4\Html;

$this->title = 'Login';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-login">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card mt-5">
                <div class="card-header bg-white">
                    <h3 class="h5 mb-0 text-center">Sign in</h3>
                </div>
                <div class="card-body p-4">
                    <div class="form">
                        <?php $form = ActiveForm::begin([
                            'id' => 'login-form',
                            'layout' => 'horizontal',
                            'fieldConfig' => [
                                'template' => "{label}\n{input}\n{error}",
                                'labelOptions' => ['class' => 'col-form-label'],
                                'inputOptions' => ['class' => 'form-control'],
                                'errorOptions' => ['class' => 'invalid-feedback'],
                            ],
                        ]); ?>

                        <?= $form->field($model, 'username')->textInput(['autofocus' => true, 'placeholder' => 'Username']) ?>

                        <?= $form->field($model, 'password')->passwordInput(['placeholder' => 'Password']) ?>
                        
                        <div class="mb-3 small">
                            Forgot password? Click <?= Html::a('here', ['site/recover-password']) ?>.
                        </div>

                        <?= $form->field($model, 'rememberMe')->checkbox([
                            'template' => "<div class=\"form-check\">{input} {label}</div>\n<div class=\"col-lg-8\">{error}</div>",
                        ]) ?>

                        <div class="form-group d-grid mt-4">
                            <?= Html::submitButton('Login', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div><!-- form -->
                </div>
            </div>
        </div>
    </div>
</div>
