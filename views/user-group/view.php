<?php

/** @var yii\web\View $this */
/** @var app\models\Usergroup $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\helpers\Html;
use app\models\Lookup;
use app\models\Usergroup;

$this->title = $model->name . ' - Groups';
$this->params['breadcrumbs'][] = ['label' => 'User Groups', 'url' => ['index']];
$this->params['breadcrumbs'][] = Html::encode($model->name);

?>

<div class="user-group-view">
    <h4>General Info:</h4>

    <div class="btn-toolbar mb-3 justify-content-end" role="toolbar">
        <div class="btn-group btn-group-sm" role="group">
            <?php if ($model->canUpdate()): ?>
                <?= Html::a('Update Group', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
            <?php endif; ?>

            <?php if ($model->status == Usergroup::STATUS_ACTIVE && $model->canDisable()): ?>
                <?= Html::a('Disable Group', ['disable', 'id' => $model->id], [
                    'class' => 'btn btn-outline-warning',
                    'data' => [
                        'confirm' => 'Are you sure you want to disable this group?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php elseif ($model->status == Usergroup::STATUS_DISABLED): ?>
                <?= Html::a('Enable Group', ['enable', 'id' => $model->id], [
                    'class' => 'btn btn-outline-success',
                    'data' => [
                        'confirm' => 'Are you sure you want to enable this group?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>

            <?php if (!$model->isStandard()): ?>
                <?= Html::a('Delete Group', ['delete', 'id' => $model->id], [
                    'class' => 'btn btn-outline-danger',
                    'data' => [
                        'confirm' => 'Are you sure you want to permanently delete this group?',
                        'method' => 'post',
                    ],
                ]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'name',
            'description',
            [
                'attribute' => 'status',
                'value' => Lookup::item('UserGroupStatus', $model->status),
            ],
        ],
    ]) ?>

    <div class="row mt-4">
        <div class="col-md-6">
            <h5>Global Permissions:</h5>
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    [
                        'attribute' => 'gperm_access_admin_panel',
                        'value' => $model->gperm_access_admin_panel ? 'Yes' : 'No',
                    ],
                ],
            ]) ?>

            <h5 class="mt-3">UI Preferences:</h5>
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    [
                        'label' => 'Default Sidebar Tab',
                        'value' => $model->getDefaultSidebarTabLabel(),
                    ],
                    [
                        'label' => 'Default Bug Status Filter',
                        'value' => $model->getDefaultBugStatusFilterLabel(),
                    ],
                ],
            ]) ?>
        </div>
        <div class="col-md-6">
            <h5>Project Permissions:</h5>
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    ['attribute' => 'pperm_browse_crash_reports', 'value' => $model->pperm_browse_crash_reports ? 'Yes' : 'No'],
                    ['attribute' => 'pperm_browse_bugs', 'value' => $model->pperm_browse_bugs ? 'Yes' : 'No'],
                    ['attribute' => 'pperm_browse_debug_info', 'value' => $model->pperm_browse_debug_info ? 'Yes' : 'No'],
                    ['attribute' => 'pperm_manage_crash_reports', 'value' => $model->pperm_manage_crash_reports ? 'Yes' : 'No'],
                    ['attribute' => 'pperm_manage_bugs', 'value' => $model->pperm_manage_bugs ? 'Yes' : 'No'],
                    ['attribute' => 'pperm_manage_debug_info', 'value' => $model->pperm_manage_debug_info ? 'Yes' : 'No'],
                ],
            ]) ?>
        </div>
    </div>

    <div class="mt-4">
        <h4>Users Belonging to this Group:</h4>
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                'id',
                [
                    'attribute' => 'username',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a(Html::encode($data->username), ['user/view', 'id' => $data->id]);
                    },
                ],
                [
                    'attribute' => 'status',
                    'value' => function ($data) {
                        return Lookup::item('UserStatus', $data->status);
                    },
                ],
            ],
        ]) ?>
    </div>
</div>
