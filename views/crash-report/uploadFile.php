<?php

/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */
/** @var bool $submitted */

use yii\bootstrap4\ActiveForm;
use yii\helpers\Html;

$this->title = 'Upload Crash Report';
$this->params['breadcrumbs'][] = ['label' => 'Crash Reports', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="crash-report-upload">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($submitted): ?>
        <div class="alert alert-success">
            Report has been uploaded and queued for processing.
        </div>
    <?php endif; ?>

    <div class="form">
        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

        <?= $form->field($model, 'fileAttachment')->fileInput() ?>

        <div class="form-group">
            <?= Html::submitButton('Upload', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
