<?php
/** @var yii\web\View $this */
/** @var app\models\User $model */
use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

$this->title = 'Create Admin Account - CrashFix Setup';
?>

<div class="install-admin my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 520px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">Initial Admin User</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted small mb-4">Create the first administrator account for your CrashFix installation.</p>

            <?php if ($model->hasErrors()): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($model->getErrors() as $attribute => $errors): ?>
                            <?php foreach ($errors as $error): ?>
                                <li><strong><?= Html::encode($model->getAttributeLabel($attribute)) ?>:</strong> <?= Html::encode($error) ?></li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php $form = ActiveForm::begin(['id' => 'admin-form']); ?>

            <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <?= Html::activeTextInput($model, 'username', ['class' => 'form-control', 'placeholder' => 'admin', 'required' => true]) ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <?= Html::activeTextInput($model, 'email', ['class' => 'form-control', 'placeholder' => 'admin@example.com', 'type' => 'email', 'required' => true]) ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <?= Html::activePasswordInput($model, 'password', ['class' => 'form-control', 'required' => true]) ?>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <?= Html::a('Back', ['migrate'], ['class' => 'btn btn-outline-secondary']) ?>
                <?= Html::submitButton('Create Account & Finish', ['class' => 'btn btn-primary px-4']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
