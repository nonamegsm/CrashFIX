<?php

/** @var yii\web\View $this */
/** @var app\models\SerialsinfoSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;

$this->title = Yii::$app->name . ' - Serials Info';
// Layout breadcrumb is rendered by AdminLTE; we add the section
// label only (not the page title) so it doesn't duplicate the H1.
$this->params['breadcrumbs'][] = ['label' => 'Administer', 'url' => ['site/admin']];
$this->params['breadcrumbs'][] = 'Serials Info';

$user = Yii::$app->user;
$myProjects = $user->getMyProjects();
?>

<style>
.cf-serials-meta { font-size: 11px; color: #666; margin-bottom: 12px; }
</style>

<?php if (count($myProjects) === 0): ?>
    <div class="alert alert-info">
        You have no projects assigned. Serials Info aggregates per-project
        crash report data; assign yourself to a project first.
    </div>
<?php else: ?>

    <div class="row mb-3" id="div_proj_selection">
        <div class="col-md-12">
            <form id="proj_form" action="<?= Url::to(['site/set-cur-project']) ?>" method="get"
                  class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label">Current Project:</label>
                </div>
                <div class="col-auto">
                    <?= Html::dropDownList(
                        'proj',
                        $user->getCurProjectId(),
                        \yii\helpers\ArrayHelper::map($myProjects, 'id', 'name'),
                        ['id' => 'proj', 'class' => 'form-select form-select-sm']
                    ) ?>
                </div>
                <div class="col-auto">
                    <label class="col-form-label">Version:</label>
                </div>
                <div class="col-auto">
                    <?php
                    $selVer = -1;
                    $versions = $user->getCurProjectVersions($selVer);
                    echo Html::dropDownList('ver', $selVer, $versions,
                        ['id' => 'ver', 'class' => 'form-select form-select-sm']);
                    ?>
                </div>
            </form>
        </div>
    </div>

    <p class="cf-serials-meta">
        Pairs of (Box Serial, Card Serial) custom-properties seen in
        crash reports, with the count of distinct crash reports each
        pair has produced. Sourced from the
        <code>view_serials_report_count</code> database view, which
        aggregates <code>tbl_customprop</code> rows where
        <code>name = 'Box Serial'</code> /
        <code>name = 'Card Serial'</code>. If your crash-reporting
        client doesn't send those custom-prop names this list will be
        empty - that's not an error.
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel'  => $searchModel,
        'columns' => [
            [
                'attribute' => 'box_serial',
                'label'     => 'Box Serial',
            ],
            [
                'attribute' => 'card_serial',
                'label'     => 'Card Serial',
            ],
            [
                'attribute' => 'report_count',
                'label'     => 'Reports',
                'contentOptions' => ['style' => 'text-align:right; width:100px;'],
                'headerOptions'  => ['style' => 'text-align:right'],
            ],
        ],
    ]); ?>

    <?php $this->registerJs(<<<JS
        $("#proj, #ver").on('change', function() {
            $("#proj_form").submit();
        });
JS
    ); ?>

<?php endif; ?>
