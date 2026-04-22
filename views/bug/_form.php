<?php

/** @var yii\web\View $this */
/** @var app\models\Bug $model */
/** @var yii\bootstrap4\ActiveForm $form */

use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use app\models\Lookup;
use app\models\Bug;

?>

<div class="bug-form">

    <?php $form = ActiveForm::begin([
        'id' => 'bug-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

    <div class="card">
        <div class="card-body">
            <?= $form->field($model, 'summary')->textInput(['maxlength' => true, 'placeholder' => 'Enter bug summary...']) ?>

            <?= $form->field($model, 'description')->textarea(['rows' => 6, 'placeholder' => 'Enter detailed description...']) ?>

            <div class="row">
                <div class="col-md-4">
                    <?= $form->field($model, 'priority')->dropDownList(Lookup::items('BugPriority')) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'reproducability')->dropDownList(Lookup::items('BugReproducability')) ?>
                </div>
                <div class="col-md-4">
                    <?php
                    $user = Yii::$app->user;
                    $projectUsers = $user->getCurProject()->users;
                    $userList = [-1 => '<nobody>'];
                    foreach ($projectUsers as $pu) {
                        if ($pu->user) {
                            $userList[$pu->user_id] = $pu->user->username;
                        }
                    }
                    echo $form->field($model, 'assigned_to')->dropDownList($userList);
                    ?>
                </div>
            </div>

            <div class="form-group mt-3">
                <?= Html::submitButton('Create Bug', ['class' => 'btn btn-success']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>

</div>
