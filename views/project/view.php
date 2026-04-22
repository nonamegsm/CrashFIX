<?php

/** @var yii\web\View $this */
/** @var app\models\Project $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use yii\grid\GridView;
use app\models\Lookup;
use app\models\Project;

$this->title = $model->name . ' - Projects';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = ['label' => 'Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = Html::encode($model->name);

?>

<div class="project-view">
    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?= Html::a('Update Project', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>

            <?php if ($model->status == Project::STATUS_ACTIVE): ?>
                <?= Html::a('Disable Project', ['disable'], [
                    'class' => 'btn btn-outline-warning',
                    'data' => ['method' => 'post', 'params' => ['id' => $model->id]]
                ]) ?>
            <?php else: ?>
                <?= Html::a('Enable Project', ['enable'], [
                    'class' => 'btn btn-outline-success',
                    'data' => ['method' => 'post', 'params' => ['id' => $model->id]]
                ]) ?>
            <?php endif; ?>

            <?= Html::a('Delete Project', ['delete'], [
                'class' => 'btn btn-outline-danger',
                'data' => [
                    'confirm' => 'Are you sure you want to permanently delete this project?',
                    'method' => 'post',
                    'params' => ['id' => $model->id]
                ]
            ]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h5>Project Info:</h5>
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'name',
                    'description',
                    [
                        'attribute' => 'status',
                        'value' => Lookup::item('ProjectStatus', $model->status),
                    ],
                ],
            ]) ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <h5>Project Quotas:</h5>
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'crash_reports_per_group_quota',
                    'crash_report_files_disc_quota',
                    'bug_attachment_files_disc_quota',
                    'debug_info_files_disc_quota',
                ],
            ]) ?>
        </div>
    </div>

    <div class="mt-4">
        <h4>Users Participating in this Project:</h4>
        <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
            <div class="btn-group btn-group-sm" role="group">
                <?= Html::a('Add User(s)', ['add-user', 'id' => $model->id], ['class' => 'btn btn-outline-success']) ?>
                <button id="delete_selected" class="btn btn-outline-danger" style="display:none">Remove Selected Users</button>
            </div>
        </div>

        <?php $form = \yii\widgets\ActiveForm::begin([
            'id' => 'del_user_form',
            'action' => ['delete-user'],
            'method' => 'post',
        ]); ?>
        <?= Html::hiddenInput('project_id', $model->id) ?>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                ['class' => 'yii\grid\CheckboxColumn', 'name' => 'DeleteRows'],
                [
                    'attribute' => 'user_id',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a(Html::encode($data->user->username), ['user/view', 'id' => $data->user_id]);
                    },
                ],
                [
                    'attribute' => 'usergroup_id',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a(Html::encode($data->usergroup->name), ['user-group/view', 'id' => $data->usergroup_id]);
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
        if(confirm('Are you sure you want to remove selected users?')) {
            $("#del_user_form").submit();
        }
    });
JS
); ?>
