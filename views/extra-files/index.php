<?php

/** @var yii\web\View $this */
/** @var app\models\ExtrafilesSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\components\MiscHelpers;
use app\models\Lookup;

$this->title = Yii::$app->name . ' - Extra Files';
$this->params['breadcrumbs'][] = ['label' => 'Administer', 'url' => ['site/admin']];
$this->params['breadcrumbs'][] = 'Extra Files';

$user = Yii::$app->user;
$myProjects = $user->getMyProjects();
?>

<?php if (count($myProjects) === 0): ?>
    <div class="alert alert-info">You have no projects assigned.</div>
<?php else: ?>

    <p class="text-muted small mb-3">
        Build a ZIP of non-standard attachments (everything except dumps, screenshots,
        <code>crashrpt.xml</code>, <code>.txt</code>, and <code>.log</code>) from crash reports
        received between two dates. Requires a working daemon for <strong>Process</strong>.
    </p>

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
                <?= Html::hiddenInput('ver', -1) ?>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Create new collection</strong></div>
        <div class="card-body">
            <form action="<?= Url::to(['create']) ?>" method="get" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label">From (dd/mm/yyyy)</label>
                    <?= Html::textInput('date_from', '', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'dd/mm/yyyy',
                        'autocomplete' => 'off',
                    ]) ?>
                </div>
                <div class="col-auto">
                    <label class="form-label">To (dd/mm/yyyy)</label>
                    <?= Html::textInput('date_to', '', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'dd/mm/yyyy',
                        'autocomplete' => 'off',
                    ]) ?>
                </div>
                <div class="col-auto">
                    <?= Html::submitButton('Create', ['class' => 'btn btn-primary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'name',
                'format' => 'raw',
                'value' => static function ($m) {
                    return Html::a(Html::encode($m->name), ['view', 'id' => $m->id]);
                },
            ],
            [
                'attribute' => 'date_from',
                'format' => 'raw',
                'filter' => false,
                'value' => static function ($m) {
                    return $m->date_from ? Yii::$app->formatter->asDatetime($m->date_from, 'php:d/m/y H:i') : '';
                },
            ],
            [
                'attribute' => 'date_to',
                'format' => 'raw',
                'filter' => false,
                'value' => static function ($m) {
                    return $m->date_to ? Yii::$app->formatter->asDatetime($m->date_to, 'php:d/m/y H:i') : '';
                },
            ],
            [
                'attribute' => 'status',
                'value' => static function ($m) {
                    return Lookup::item('CrashReportStatus', (int) $m->status) ?: $m->status;
                },
                'filter' => Lookup::items('CrashReportStatus'),
                'contentOptions' => static function ($m) {
                    return (int) $m->status === \app\models\Extrafiles::STATUS_INVALID
                        ? ['class' => 'table-danger']
                        : [];
                },
            ],
            [
                'label' => 'Download',
                'format' => 'raw',
                'value' => static function ($m) {
                    if ($m->path && is_file($m->path)) {
                        return Html::a(
                            Html::encode($m->name . '_' . $m->id . '.zip'),
                            ['download', 'id' => $m->id]
                        );
                    }
                    return '';
                },
            ],
        ],
    ]); ?>

    <?php
    $cur = $user->getCurProject();
    if ($cur !== null) {
        $totalFileSize = 0;
        $percentOfQuota = 0;
        $count = $cur->getCrashReportCount($totalFileSize, $percentOfQuota);
        $totalFileSizeStr = MiscHelpers::fileSizeToStr($totalFileSize);
        $percentOfQuotaStr = sprintf('%.0f', $percentOfQuota);
        $foot = "This project contains total {$totalFileSizeStr} in {$count} file(s)";
        if ($percentOfQuota >= 0) {
            $foot .= " ({$percentOfQuotaStr}% of disc quota).";
        }
        echo Html::tag('p', Html::encode($foot), ['class' => 'text-muted small mt-3']);
    }
    ?>

    <?php
    $this->registerJs(<<<'JS'
$("#proj").on("change", function () { $("#proj_form").trigger("submit"); });
JS
    );
    ?>

<?php endif; ?>
