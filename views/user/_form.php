<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */
/** @var yii\widgets\ActiveForm $form */

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use app\models\Usergroup;

?>

<div class="user-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'username')->textInput(['maxlength' => true, 'readonly' => !$model->isNewRecord]) ?>

    <?php if ($model->isNewRecord): ?>
        <?= $form->field($model, 'password')->passwordInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'password_repeat')->passwordInput(['maxlength' => true]) ?>
    <?php endif; ?>

    <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'usergroup')->dropDownList(
        \yii\helpers\ArrayHelper::map(Usergroup::find()->all(), 'id', 'name')
    ) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
