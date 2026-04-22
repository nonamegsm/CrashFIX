<?php

/** @var yii\web\View $this */
/** @var app\models\Project $model */

use yii\helpers\Html;

$this->title = 'Update Project: ' . $model->name;
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="project-update">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
