<?php

/** @var yii\web\View $this */
/** @var app\models\CrashreportSearch $model */

use app\models\Lookup;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$statusItems = ['' => 'Any status'] + Lookup::items('CrashReportStatus');
?>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header">
        <h3 class="card-title mb-0">Filter reports</h3>
    </div>
    <div class="card-body">
        <?php $form = ActiveForm::begin([
            'action' => ['index'],
            'method' => 'get',
            'options' => ['class' => 'crash-report-search'],
        ]); ?>

        <div class="row">
            <div class="col-md-2">
                <?= $form->field($model, 'id')->textInput(['type' => 'number', 'class' => 'form-control form-control-sm'])->label('ID') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'status')->dropDownList($statusItems, ['class' => 'form-select form-select-sm'])->label('Status') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'groupid')->textInput(['type' => 'number', 'class' => 'form-control form-control-sm'])->label('Group ID') ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'crashguid')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'md5')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'srcfilename')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($model, 'ipaddress')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm'])->label('IP address') ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'emailfrom')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'filesize')->textInput(['type' => 'number', 'class' => 'form-control form-control-sm'])->label('File size (bytes)') ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <?= $form->field($model, 'description')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'exception_type')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($model, 'exceptionmodule')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'exe_image')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'os_name_reg')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'geo_location')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm']) ?>
            </div>
        </div>

        <div class="form-group mb-0">
            <?= Html::submitButton('Search', ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Reset', ['index'], ['class' => 'btn btn-outline-secondary btn-sm ms-1']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
