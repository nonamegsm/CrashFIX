<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var app\models\Project[] $projects */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use app\models\Usergroup;
use yii\helpers\ArrayHelper;
use app\models\UserProjectAccess;

$this->title = 'Add Project Roles';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Users', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => Html::encode($user->username), 'url' => ['view', 'id' => $user->id]];
$this->params['breadcrumbs'][] = $this->title;

$usergroups = Usergroup::find()->all();
$roles = ArrayHelper::map($usergroups, 'id', 'name');

// Get current roles for this user
$currentRoles = ArrayHelper::map(UserProjectAccess::find()->where(['user_id' => $user->id])->all(), 'project_id', 'usergroup_id');

?>

<div class="user-add-project-role">

    <div class="alert alert-info">
        Check the boxes next to the names of projects in which the user must 
        participate, select their role, and then click the Save button to apply your changes.
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'add-project-role-form']); ?>

            <?= Html::hiddenInput('id', $user->id) ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px; text-align: center;"></th>
                            <th>Project Name</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <?php 
                                $isChecked = isset($currentRoles[$project->id]); 
                                $currentSelectedRole = $isChecked ? $currentRoles[$project->id] : 4; // Default to Developer (ID 4)
                            ?>
                            <tr>
                                <td style="text-align: center;">
                                    <?= Html::checkbox('check[]', $isChecked, ['value' => $project->id]) ?>
                                </td>
                                <td>
                                    <?= Html::a(Html::encode($project->name), ['/project/view', 'id' => $project->id], ['class' => 'text-decoration-none fw-bold']) ?>
                                </td>
                                <td style="width: 300px;">
                                    <?= Html::dropDownList("Project[{$project->id}][role]", $currentSelectedRole, $roles, ['class' => 'form-select form-select-sm']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group mt-4 text-end">
                <?= Html::a('Cancel', ['view', 'id' => $user->id], ['class' => 'btn btn-secondary me-2']) ?>
                <?= Html::submitButton('Save Roles', ['class' => 'btn btn-success px-4']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

</div>
