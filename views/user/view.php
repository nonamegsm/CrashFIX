<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */
/** @var yii\data\ActiveDataProvider $userProjectAccess */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use yii\grid\GridView;
use app\models\User;

if (Yii::$app->user->can('gperm_access_admin_panel')) {
    $this->params['breadcrumbs'][] = 'Administer';
    $this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index']];
} else {
    $this->params['breadcrumbs'][] = 'Users';
}
$this->params['breadcrumbs'][] = Html::encode($model->username);
$this->title = Html::encode($model->username) . ' - Users';

?>

<div class="user-view">
    <h4>General Info:</h4>

    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?php if (Yii::$app->user->id == $model->id): ?>
                <?= Html::a('Change Password', ['site/reset-password'], [
                    'class' => 'btn btn-outline-primary',
                    'data' => [
                        'confirm' => 'Are you sure you want to change your password?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>

            <?php if (Yii::$app->user->can('gperm_access_admin_panel')): ?>
                <?= Html::a('Update User', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>

                <?php if (!$model->isStandard()): ?>
                    <?php if ($model->getEffectiveStatus() != User::STATUS_DISABLED): ?>
                        <?= Html::a('Disable User', ['retire', 'id' => $model->id], [
                            'class' => 'btn btn-outline-warning',
                            'data' => [
                                'confirm' => "Are you sure you want to set status of user {$model->username} to 'Retired'?",
                                'method' => 'post',
                            ],
                        ]) ?>
                    <?php else: ?>
                        <?= Html::a('Enable User', ['resurrect', 'id' => $model->id], [
                            'class' => 'btn btn-outline-success',
                            'data' => [
                                'confirm' => "Are you sure you want to set status of user {$model->username} to 'Active'?",
                                'method' => 'post',
                            ],
                        ]) ?>
                    <?php endif; ?>

                    <?= Html::a('Delete User', ['delete', 'id' => $model->id], [
                        'class' => 'btn btn-outline-danger',
                        'data' => [
                            'confirm' => "Are you sure you want to permanently delete user {$model->username}?",
                            'method' => 'post',
                        ],
                    ]) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'username',
            [
                'label' => 'User Group',
                'format' => 'raw',
                'value' => function($model) {
                    return Yii::$app->user->can('gperm_access_admin_panel') ? Html::a(Html::encode($model->group->name), ['/user-group/view', 'id' => $model->usergroup]) : Html::encode($model->group->name);
                },
            ],
            [
                'label' => 'Status',
                'value' => $model->getEffectiveStatusStr(),
            ],
            'email:email',
        ],
    ]) ?>

    <div class="mt-4">
        <h4>Project Roles:</h4>
        <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
            <div class="btn-group btn-group-sm" role="group">
                <?php if (Yii::$app->user->can('gperm_access_admin_panel')): ?>
                    <?= Html::a('Add Project Role(s)', ['add-project-role', 'id' => $model->id], ['class' => 'btn btn-outline-success']) ?>
                    <button id="delete_selected" class="btn btn-outline-danger" style="display:none">Delete Selected Roles</button>
                <?php endif; ?>
            </div>
        </div>

        <?php $form = \yii\widgets\ActiveForm::begin([
            'id' => 'del_role_form',
            'action' => ['delete-project-roles'],
            'method' => 'post',
        ]); ?>
        <?= Html::hiddenInput('user_id', $model->id) ?>

        <?= GridView::widget([
            'dataProvider' => $userProjectAccess,
            'columns' => [
                ['class' => 'yii\grid\CheckboxColumn', 'name' => 'DeleteRows'],
                [
                    'label' => 'Project',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Yii::$app->user->can('gperm_access_admin_panel') ? Html::a(Html::encode($data->project->name), ['/project/view', 'id' => $data->project_id]) : Html::encode($data->project->name);
                    },
                ],
                [
                    'label' => 'Project Role',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Yii::$app->user->can('gperm_access_admin_panel') ? Html::a(Html::encode($data->usergroup->name), ['/user-group/view', 'id' => $data->usergroup_id]) : Html::encode($data->usergroup->name);
                    },
                ],
            ],
        ]); ?>
        <?php \yii\widgets\ActiveForm::end(); ?>
    </div>
</div>

<?php $this->registerJs(<<<JS
    $(document).on('change', 'input[name="DeleteRows[]"]', function() {
        var totalSelected = $('input[name="DeleteRows[]"]:checked').length;
        if(totalSelected == 0) {
            $("#delete_selected").hide();
        } else {
            $("#delete_selected").show();
        }
    });

    $("#delete_selected").on('click', function() {
        if(confirm('Are you sure you want to delete selected roles?')) {
            $("#del_role_form").submit();
        }
    });
JS
); ?>
