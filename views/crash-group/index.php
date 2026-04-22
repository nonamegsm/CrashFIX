<?php

/** @var yii\web\View $this */
/** @var app\models\Crashgroup $model */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\components\MiscHelpers;

$this->title = Yii::$app->name . ' - Browse Crash Report Collections';
$this->params['breadcrumbs'][] = 'Crash Report Collections';

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
            <form action="<?= Url::to(['crash-group/index']) ?>" method="get" class="row g-3">
                <div class="col-md-3">
                    <?= Html::dropDownList('status', Yii::$app->request->get('status', 'open'), [
                        'all' => 'All collections',
                        'open' => 'Nonempty c. with bug(s) unassigned or open',
                    ], ['class' => 'form-select']) ?>
                </div>
                <div class="col-md-7">
                    <?= Html::textInput('q', Yii::$app->request->get('q'), ['placeholder' => 'Search collections by title', 'class' => 'form-control']) ?>
                </div>
                <div class="col-md-2">
                    <?= Html::submitButton('Search', ['class' => 'btn btn-primary w-100']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <?php if ($user->can('pperm_manage_crash_reports', ['project_id' => $user->getCurProjectId()])): ?>
            <button id="delete_selected" class="btn btn-danger btn-sm" style="display:none">Delete Selected</button>
        <?php endif; ?>
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
                    'attribute' => 'title',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return Html::a(Html::encode(MiscHelpers::addEllipsis($data->title, 120)), ['view', 'id' => $data->id]);
                    },
                ],
                'crashReportCount',
                [
                    'label' => 'Distinct IPs',
                    'value' => function ($data) {
                        return $data->getDistinctIPs();
                    }
                ],
                [
                    'label' => 'Bug(s)',
                    'format' => 'raw',
                    'value' => function ($data) {
                        return $data->formatBugListStr();
                    }
                ],
                [
                    'attribute' => 'created',
                    'value' => function ($data) {
                        return date("d/m/y H:i", $data->created);
                    },
                ],
            ],
        ]); ?>
    </div>
    <?php \yii\widgets\ActiveForm::end(); ?>

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
            if(confirm('Are you sure you want to permanently delete selected collection(s) with all crash reports they contain?')) {
                $("#del_form").submit();
            }
        });
JS
    ); ?>

<?php endif; ?>
