<?php

/** @var yii\web\View $this */
/** @var app\models\Debuginfo $model */
/** @var bool $submitted */

use yii\bootstrap4\ActiveForm;
use yii\helpers\Html;

$this->title = 'Upload Debug Info';
$this->params['breadcrumbs'][] = ['label' => 'Debug Info', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<div class="debug-info-upload">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if ($submitted): ?>
        <div class="alert alert-success">
            Debug info file has been uploaded and queued for processing.
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
