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
            <strong>
                <?= Html::encode($model->filename ?: 'File') ?> uploaded
                <?php if ((int) $model->filesize > 0): ?>
                    (<?= \app\components\MiscHelpers::fileSizeToStr((int) $model->filesize) ?>)
                <?php endif; ?>.
            </strong>
            <div class="text-muted fst-italic small mt-1">
                Detected format:
                <?= Html::encode($model->getFormatStr()) ?>
            </div>
            <div class="small mt-1">
                Status: queued for processing.
            </div>
        </div>
    <?php endif; ?>

    <div class="form">
        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

        <?= $form->field($model, 'fileAttachment')->fileInput()->hint(
            'Supported formats: PDB, DWARF in ELF (.so / .debug), '
            . 'DWARF in PE (.exe / .dll), stripped .debug companion files. '
            . 'Format detection runs server-side after upload.'
        ) ?>

        <div class="form-group">
            <?= Html::submitButton('Upload', ['class' => 'btn btn-primary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
