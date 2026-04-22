<?php

/** @var yii\web\View $this */
/** @var app\models\Debuginfo $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\components\MiscHelpers;
use app\models\Debuginfo;

$this->title = Yii::$app->name . ' - Browse Debug Info';
$this->params['breadcrumbs'][] = 'Debug Info Files';

$user = Yii::$app->user;
$myProjects = $user->getMyProjects();

?>

<?php if (count($myProjects) == 0): ?>
    <div class="alert alert-info">
        You have no projects assigned.
    </div>
<?php else: ?>

    <div class="row mb-3" id="div_proj_selection">
        <div class="col-md-12">
            <form id="proj_form" action="<?= Url::to(['site/set-cur-project']) ?>" method="get" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label">Current Project:</label>
                </div>
                <div class="col-auto">
                    <?= Html::dropDownList('proj', $user->getCurProjectId(), \yii\helpers\ArrayHelper::map($myProjects, 'id', 'name'), ['id' => 'proj', 'class' => 'form-select form-select-sm']) ?>
                </div>
                <div class="col-auto">
                    <label class="col-form-label">Version:</label>
                </div>
                <div class="col-auto">
                    <?php
                    $selVer = -1;
                    $versions = $user->getCurProjectVersions($selVer);
                    echo Html::dropDownList('ver', $selVer, $versions, ['id' => 'ver', 'class' => 'form-select form-select-sm']);
                    ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3" id="div_simple_search">
        <div class="col-md-12">
            <form action="<?= Url::to(['debug-info/index']) ?>" method="get" class="row g-3">
                <div class="col-md-10">
                    <?= Html::textInput('q', Yii::$app->request->get('q'), ['placeholder' => 'Search by file name/GUID', 'class' => 'form-control']) ?>
                </div>
                <div class="col-md-2">
                    <?= Html::submitButton('Search', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <?= Html::a('Upload New File', ['upload-file'], ['class' => 'btn btn-success btn-sm']) ?>
        <button id="delete_selected" class="btn btn-danger btn-sm" style="display:none">Delete Selected</button>
    </div>

    <?php $form = \yii\widgets\ActiveForm::begin([
        'id' => 'del_form',
        'action' => ['delete-multiple'],
        'method' => 'post',
    ]); ?>

    <div class="grid-view">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                ['class' => 'yii\grid\CheckboxColumn', 'name' => 'DeleteRows'],
                'id',
                [
                    'attribute' => 'filename',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a(Html::encode($data->filename), ['view', 'id' => $data->id]);
                    },
                ],
                [
                    'attribute' => 'filesize',
                    'value' => function ($data) {
                        return MiscHelpers::fileSizeToStr($data->filesize);
                    },
                ],
                [
                    'attribute' => 'status',
                    'value' => function ($data) {
                        return $data->getStatusStr();
                    },
                    'contentOptions' => function ($data) {
                        return $data->status == Debuginfo::STATUS_INVALID ? ['class' => 'text-danger'] : [];
                    }
                ],
                [
                    'attribute' => 'guid',
                    'value' => function ($data) {
                        return (isset($data->guid) && substr($data->guid, 0, 4) != "tmp_") ? $data->guid : "n/a";
                    }
                ],
                [
                    'attribute' => 'dateuploaded',
                    'value' => function ($data) {
                        return date("d/m/y H:i", $data->dateuploaded);
                    },
                ],
            ],
        ]); ?>
    </div>
    <?php \yii\widgets\ActiveForm::end(); ?>

    <div class="mt-3 text-muted small">
        <?php
        $totalFileSize = 0;
        $percentOfQuota = 0;
        $count = $user->getCurProject()->getDebugInfoCount($totalFileSize, $percentOfQuota);
        $totalFileSizeStr = MiscHelpers::fileSizeToStr($totalFileSize);
        echo "This project contains total $totalFileSizeStr in $count file(s)";
        if ($percentOfQuota >= 0) echo " (" . sprintf("%.0f", $percentOfQuota) . "% of disk quota).";
        ?>
    </div>

    <?php $this->registerJs(<<<JS
        $("#proj, #ver").on('change', function() {
            $("#proj_form").submit();
        });

        $(document).on('change', 'input[name="DeleteRows[]"]', function() {
            var totalSelected = $('input[name="DeleteRows[]"]:checked').length;
            if(totalSelected == 0) {
                $("#delete_selected").hide();
            } else {
                $("#delete_selected").show();
            }
        });

        $("#delete_selected").on('click', function() {
            if(confirm('Are you sure you want to permanently delete selected debug info file(s)?')) {
                $("#del_form").submit();
            }
        });
JS
    ); ?>

<?php endif; ?>
