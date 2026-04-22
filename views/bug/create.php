<?php

/** @var yii\web\View $this */
/** @var app\models\Bug $model */

use yii\helpers\Html;

$this->title = 'Create Bug';
$this->params['breadcrumbs'][] = ['label' => 'Bugs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="bug-create">
    <h1 class="h4 mb-4"><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
