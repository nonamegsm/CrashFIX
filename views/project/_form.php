<?php

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\widgets\ActiveForm $form */

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;

?>

<div class="project-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <div class="row">
        <div class="col-md-6"><?= $form->field($model, 'crash_reports_per_group_quota')->textInput() ?></div>
        <div class="col-md-6"><?= $form->field($model, 'crash_report_files_disc_quota')->textInput() ?></div>
    </div>

    <div class="row">
        <div class="col-md-6"><?= $form->field($model, 'bug_attachment_files_disc_quota')->textInput() ?></div>
        <div class="col-md-6"><?= $form->field($model, 'debug_info_files_disc_quota')->textInput() ?></div>
    </div>

    <?= $form->field($model, 'require_exact_build_age')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
