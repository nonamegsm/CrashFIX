<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */

use yii\helpers\Html;

$this->title = 'Add New User';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
