<?php

/** @var yii\web\View $this */
/** @var app\models\CrashreportSearch $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::$app->name . ' - Browse Crash Reports';
$this->params['breadcrumbs'][] = 'Crash Reports';

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

    <?= $this->render('_search', ['model' => $model]) ?>

    <?= $this->render('_reportList', [
        'model' => $model,
        'dataProvider' => $dataProvider,
    ]) ?>

    <div class="mt-3 text-muted small">
        <?php
        $totalFileSize = 0;
        $percentOfQuota = 0;
        $count = $user->getCurProject()->getCrashReportCount($totalFileSize, $percentOfQuota);
        $totalFileSizeStr = \app\components\MiscHelpers::fileSizeToStr($totalFileSize);
        echo "This project contains total $totalFileSizeStr in $count file(s)";
        if ($percentOfQuota >= 0) echo " (" . sprintf("%.0f", $percentOfQuota) . "% of disk quota).";
        ?>
    </div>

    <?php $this->registerJs(<<<JS
        $("#proj, #ver").on('change', function() {
            $("#proj_form").submit();
        });
JS
    ); ?>

<?php endif; ?>
