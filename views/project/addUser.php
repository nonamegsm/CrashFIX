<?php

/** @var yii\web\View $this */
/** @var app\models\Project $project */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\grid\GridView;
use yii\bootstrap4\ActiveForm;
use app\models\Usergroup;

$this->title = 'Add User(s) to Project - ' . $project->name;
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $project->name, 'url' => ['view', 'id' => $project->id]];
$this->params['breadcrumbs'][] = 'Add User(s)';

?>

<div class="project-add-user">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            [
                'class' => 'yii\grid\CheckboxColumn',
                'name' => 'check',
                'checkboxOptions' => function ($model, $key, $index, $column) use ($project) {
                    $exists = \app\models\UserProjectAccess::findOne(['user_id' => $model->id, 'project_id' => $project->id]);
                    return ['value' => $model->id, 'checked' => $exists !== null];
                }
            ],
            'username',
            [
                'header' => 'Role',
                'format' => 'raw',
                'value' => function ($model, $key, $index) use ($project) {
                    $access = \app\models\UserProjectAccess::findOne(['user_id' => $model->id, 'project_id' => $project->id]);
                    $selected = $access ? $access->usergroup_id : null;
                    return Html::dropDownList("User[$model->id][usergroup]", $selected, 
                        \yii\helpers\ArrayHelper::map(Usergroup::find()->all(), 'id', 'name'),
                        ['class' => 'form-control form-control-sm']
                    );
                }
            ],
        ],
    ]); ?>

    <div class="form-group mt-3">
        <?= Html::submitButton('Save Changes', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
