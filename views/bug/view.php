<?php

/** @var yii\web\View $this */
/** @var app\models\Bug $model */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap4\ActiveForm;
use app\models\Lookup;
use app\models\Bug;

$this->title = 'View Bug #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Bugs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$user = Yii::$app->user;
$curProjectId = $user->getCurProjectId();

?>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="btn-toolbar justify-content-end" role="toolbar">
            <div class="btn-group btn-group-sm" role="group">
                <?php if ($user->can('pperm_manage_bugs', ['project_id' => $curProjectId])): ?>
                    <button id="link-make-changes" class="btn btn-outline-primary">Comment/Change</button>
                    <?= Html::a('Delete Bug', ['delete', 'id' => $model->id], [
                        'class' => 'btn btn-outline-danger',
                        'data' => [
                            'confirm' => 'Are you sure you want to permanently delete the bug #' . $model->id . '?',
                            'method' => 'post',
                        ],
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 <?= $model->status <= Bug::STATUS_OPEN_MAX ? 'border-primary' : 'border-secondary' ?>">
    <div class="card-header <?= $model->status <= Bug::STATUS_OPEN_MAX ? 'bg-primary text-white' : 'bg-secondary text-white' ?>">
        <h5 class="mb-0"><?= Html::encode($model->summary) ?></h5>
    </div>
    <div class="card-body">
        <div class="row mb-3 text-muted small">
            <div class="col-md-6">
                Reported by <strong><?= Html::encode($model->reporter->username) ?></strong> on <?= date('j F Y, G:i', $model->date_created) ?>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-info text-dark">Status: <?= Lookup::item('BugStatus', $model->status) ?></span>
                <span class="badge bg-warning text-dark">Priority: <?= Lookup::item('BugPriority', $model->priority) ?></span>
                <span class="badge bg-info text-dark">Owner: <?= $model->assigned_to < 0 ? 'nobody' : ($model->owner ? Html::encode($model->owner->username) : 'unknown') ?></span>
            </div>
        </div>

        <div class="mb-3 p-3 bg-light border rounded">
            <?= nl2br(Html::encode($model->description)) ?>
        </div>

        <?php if (!empty($model->bugchanges)): ?>
            <div class="mt-4">
                <h6>Changes &amp; Comments:</h6>
                <?php foreach ($model->bugchanges as $change): ?>
                    <?= $this->render('/site/_bugChange', ['model' => $change]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="div-changes" style="display: <?= $model->hasErrors() ? 'block' : 'none' ?>" class="mt-4">
    <div class="card">
        <div class="card-header">Add a comment and make changes</div>
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'id' => 'bug-form',
                'options' => ['enctype' => 'multipart/form-data']
            ]); ?>

            <?= $form->field($model, 'comment')->textarea(['rows' => 4]) ?>
            <?= $form->field($model, 'fileAttachment')->fileInput() ?>
            
            <div class="row">
                <div class="col-md-6"><?= $form->field($model, 'summary')->textInput() ?></div>
                <div class="col-md-6"><?= $form->field($model, 'status')->dropDownList(Lookup::items('BugStatus', Bug::STATUS_OPEN_MAX)) ?></div>
            </div>

            <div class="row">
                <div class="col-md-4"><?= $form->field($model, 'priority')->dropDownList(Lookup::items('BugPriority')) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'reproducability')->dropDownList(Lookup::items('BugReproducability')) ?></div>
                <div class="col-md-4">
                    <?php
                    $projectUsers = $user->getCurProject()->users;
                    $userList = [-1 => '<nobody>'];
                    foreach ($projectUsers as $pu) $userList[$pu->user_id] = $pu->user->username;
                    echo $form->field($model, 'assigned_to')->dropDownList($userList);
                    ?>
                </div>
            </div>

            <div class="form-group mt-3">
                <?= Html::submitButton('Save Changes', ['class' => 'btn btn-primary']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php $this->registerJs(<<<JS
    $("#link-make-changes").on('click', function() {
        $("#div-changes").toggle();
        if($("#div-changes").is(":visible")) {
            $('html,body').animate({scrollTop: $("#div-changes").offset().top}, 'fast');
        }
    });
JS
); ?>
