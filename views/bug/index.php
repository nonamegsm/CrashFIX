<?php

/** @var yii\web\View $this */
/** @var app\models\Bug $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\models\Lookup;
use app\components\MiscHelpers;

$this->title = Yii::$app->name . ' - Browse Bugs';
$this->params['breadcrumbs'][] = 'Bugs';

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
            <form id="proj_form" action="<?= Url::to(['site/set-cur-project']) ?>" method="get" class="form-row align-items-center">
                <div class="col-auto">
                    <label class="col-form-label">Current Project:</label>
                </div>
                <div class="col-auto">
                    <?= Html::dropDownList('proj', $user->getCurProjectId(), \yii\helpers\ArrayHelper::map($myProjects, 'id', 'name'), ['id' => 'proj', 'class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-auto">
                    <label class="col-form-label">Version:</label>
                </div>
                <div class="col-auto">
                    <?php
                    $selVer = -1;
                    $versions = $user->getCurProjectVersions($selVer);
                    echo Html::dropDownList('ver', $selVer, $versions, ['id' => 'ver', 'class' => 'form-control form-control-sm']);
                    ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3" id="div_simple_search">
        <div class="col-md-12">
            <form action="<?= Url::to(['bug/index']) ?>" method="get" class="form-row">
                <div class="col-md-3">
                    <?= Html::dropDownList('status', Yii::$app->request->get('status', 'open'), [
                        'all' => 'All bugs',
                        'open' => 'Open bugs',
                        'owned' => 'Open and owned by me',
                        'reported' => 'Open and reported by me',
                        'verify' => 'Bugs to verify',
                        'closed' => 'Closed bugs',
                    ], ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-7">
                    <?= Html::textInput('q', Yii::$app->request->get('q'), ['placeholder' => 'Search by Summary/Reporter/Owner', 'class' => 'form-control']) ?>
                </div>
                <div class="col-md-2">
                    <?= Html::submitButton('Search', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <?php if ($user->can('pperm_manage_bugs', ['project_id' => $user->getCurProjectId()])): ?>
            <?= Html::a('Open New Bug', ['bug/create'], ['class' => 'btn btn-success btn-sm']) ?>
        <?php endif; ?>
    </div>

    <div class="grid-view">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'columns' => [
                ['class' => 'yii\grid\CheckboxColumn', 'name' => 'DeleteRows'],
                [
                    'attribute' => 'id',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a($data->id, ['view', 'id' => $data->id]);
                    },
                ],
                [
                    'attribute' => 'status',
                    'value' => function ($data) {
                        return Lookup::item('BugStatus', $data->status);
                    },
                ],
                [
                    'attribute' => 'priority',
                    'value' => function ($data) {
                        return Lookup::item('BugPriority', $data->priority);
                    },
                ],
                [
                    'attribute' => 'date_created',
                    'value' => function ($data) {
                        return date("d/m/y H:i", $data->date_created);
                    },
                ],
                [
                    'label' => 'Reporter',
                    'value' => function ($data) {
                        return $data->reporter ? $data->reporter->username : '';
                    },
                ],
                [
                    'label' => 'Owner',
                    'value' => function ($data) {
                        return $data->owner ? $data->owner->username : '';
                    },
                ],
                [
                    'attribute' => 'summary',
                    'value' => function ($data) {
                        return MiscHelpers::addEllipsis($data->summary, 100);
                    },
                ],
            ],
        ]); ?>
    </div>

    <div class="mt-3 text-muted small">
        <?php
        $totalFileSize = 0;
        $percentOfQuota = 0;
        $count = $user->getCurProject()->getBugAttachmentCount($totalFileSize, $percentOfQuota);
        $totalFileSizeStr = MiscHelpers::fileSizeToStr($totalFileSize);
        echo "This project contains total $totalFileSizeStr in $count bug file attachment(s)";
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
