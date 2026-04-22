<?php

/** @var yii\web\View $this */
/** @var app\models\Usergroup $model */
/** @var yii\widgets\ActiveForm $form */

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

?>

<div class="user-group-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
    <?= $form->field($model, 'description')->textarea(['rows' => 3]) ?>

    <div class="row">
        <div class="col-md-4">
            <h5>Global Permissions</h5>
            <?= $form->field($model, 'gperm_access_admin_panel')->checkbox() ?>
        </div>
        <div class="col-md-8">
            <h5>Project Permissions</h5>
            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'pperm_browse_crash_reports')->checkbox() ?>
                    <?= $form->field($model, 'pperm_browse_bugs')->checkbox() ?>
                    <?= $form->field($model, 'pperm_browse_debug_info')->checkbox() ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'pperm_manage_crash_reports')->checkbox() ?>
                    <?= $form->field($model, 'pperm_manage_bugs')->checkbox() ?>
                    <?= $form->field($model, 'pperm_manage_debug_info')->checkbox() ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <?= $form->field($model, 'default_sidebar_tab')->dropDownList($model->getSidebarTabs()) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'default_bug_status_filter')->dropDownList($model->getBugStatusFilters()) ?>
        </div>
    </div>

    <div class="form-group mt-3">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
