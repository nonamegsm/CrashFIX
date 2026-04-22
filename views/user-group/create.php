<?php

/** @var yii\web\View $this */
/** @var app\models\Usergroup $model */

use yii\helpers\Html;

$this->title = 'Create Group';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Groups', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-group-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
