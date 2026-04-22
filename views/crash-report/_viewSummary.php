<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\DetailView;
use app\components\MiscHelpers;
use app\models\Bug;
use app\models\Crashreport;
use app\models\Lookup;

$user           = Yii::$app->user;
$processingErrs = $model->getProcessingErrors()->all();
$exceptionThread = $model->getExceptionThread();

/**
 * Format a 64-bit address as 0xHEX, returning an empty string for null/zero.
 */
$asHex = function ($v): string {
    if ($v === null || $v === '' || (int) $v === 0) {
        return '';
    }
    return '0x' . strtoupper(base_convert((string) $v, 10, 16));
};
?>

<?php if (!empty($processingErrs)): ?>
    <div class="alert alert-danger">
        <strong>There were some processing errors:</strong>
        <ul class="mb-0">
            <?php foreach ($processingErrs as $err): ?>
                <li><?= Html::encode($err->message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-end mb-3">
    <div class="btn-group btn-group-sm" role="group">
        <?php if ($user->can('pperm_manage_bugs', ['project_id' => $model->project_id]) && $model->canOpenNewBug()): ?>
            <?= Html::a('Open Bug', ['bug/create', 'crashreport' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
        <?php endif; ?>

        <?php if ($user->can('pperm_manage_crash_reports', ['project_id' => $model->project_id])): ?>
            <?php if ($model->canResetStatus()): ?>
                <?= Html::a('Process Again', ['process-again'], [
                    'class' => 'btn btn-outline-secondary',
                    'data'  => [
                        'method'  => 'post',
                        'params'  => ['id' => $model->id],
                        'confirm' => 'Are you sure you want to re-process crash report #' . $model->id . '?',
                    ],
                ]) ?>
            <?php endif; ?>
            <?= Html::a('Delete Report', ['delete'], [
                'class' => 'btn btn-outline-danger',
                'data'  => [
                    'method'  => 'post',
                    'params'  => ['id' => $model->id],
                    'confirm' => 'Are you sure you want to permanently delete crash report #' . $model->id . '?',
                ],
            ]) ?>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>General</strong></div>
            <div class="card-body p-0">
                <?= DetailView::widget([
                    'model'      => $model,
                    'options'    => ['class' => 'table table-sm table-striped mb-0'],
                    'attributes' => [
                        'id',
                        [
                            'attribute' => 'received',
                            'value'     => $model->received ? date('d/m/y H:i', (int) $model->received) : '',
                        ],
                        [
                            'attribute' => 'date_created',
                            'value'     => $model->date_created ? date('d/m/y H:i', (int) $model->date_created) : '',
                        ],
                        [
                            'attribute' => 'status',
                            'value'     => Lookup::item('CrashReportStatus', (int) $model->status) ?: $model->status,
                        ],
                        [
                            'attribute' => 'filesize',
                            'value'     => MiscHelpers::fileSizeToStr((int) $model->filesize),
                        ],
                        [
                            'label' => 'Project',
                            'value' => $model->project ? $model->project->name : '',
                        ],
                        [
                            'label' => 'Version',
                            'value' => $model->appversion ? $model->appversion->version : 'unknown',
                        ],
                        'crashguid',
                        [
                            'label' => 'CrashRpt Version',
                            'value' => 'CrashRpt ' . Crashreport::generatorVersionToStr($model->crashrptver),
                        ],
                        [
                            'label'  => 'Source File',
                            'format' => 'raw',
                            'value'  => Html::a(Html::encode($model->srcfilename), ['download', 'id' => $model->id]),
                        ],
                        [
                            'label'  => 'Crash Group',
                            'format' => 'raw',
                            'value'  => $model->crashGroup
                                ? Html::a(Html::encode($model->crashGroup->title), ['crash-group/view', 'id' => $model->groupid])
                                : '<span class="text-muted">ungrouped</span>',
                        ],
                        [
                            'label'  => 'Open Bug(s)',
                            'format' => 'raw',
                            'value'  => function () use ($model): string {
                                $links = [];
                                foreach ($model->bugs as $link) {
                                    $bug = $link->bug ?? null;
                                    if ($bug && (int) $bug->status < Bug::STATUS_OPEN_MAX) {
                                        $links[] = Html::a('#' . $bug->id, ['bug/view', 'id' => $bug->id]);
                                    }
                                }
                                return $links ? implode(' ', $links) : '<span class="text-muted">none</span>';
                            },
                        ],
                    ],
                ]) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Sender Info</strong></div>
            <div class="card-body p-0">
                <?= DetailView::widget([
                    'model'      => $model,
                    'options'    => ['class' => 'table table-sm table-striped mb-0'],
                    'attributes' => [
                        [
                            'attribute' => 'geo_location',
                            'value'     => Crashreport::geoIdToCountryName($model->geo_location),
                        ],
                        [
                            'attribute' => 'ipaddress',
                            'label'     => 'IP Address',
                        ],
                        'emailfrom',
                        [
                            'attribute' => 'description',
                            'format'    => 'ntext',
                        ],
                    ],
                ]) ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Exception Info</strong></div>
            <div class="card-body p-0">
                <?= DetailView::widget([
                    'model'      => $model,
                    'options'    => ['class' => 'table table-sm table-striped mb-0'],
                    'attributes' => [
                        'exception_type',
                        [
                            'attribute' => 'exceptionaddress',
                            'label'     => 'Exception Address',
                            'value'     => $asHex($model->exceptionaddress),
                        ],
                        [
                            'attribute' => 'exception_code',
                            'value'     => $asHex($model->exception_code),
                        ],
                        'exe_image',
                        'exceptionmodule',
                        [
                            'attribute' => 'exceptionmodulebase',
                            'value'     => $asHex($model->exceptionmodulebase),
                        ],
                        [
                            'attribute' => 'exception_thread_id',
                            'format'    => 'raw',
                            'value'     => function () use ($model, $exceptionThread): string {
                                if (!$model->exception_thread_id) {
                                    return '';
                                }
                                $hex = '0x' . dechex((int) $model->exception_thread_id);
                                if ($exceptionThread === null) {
                                    return Html::encode($hex);
                                }
                                return Html::encode($hex) . ' &mdash; ' . Html::a(
                                    'View Stack Trace',
                                    ['view', 'id' => $model->id, 'tab' => 'Threads', 'thread' => $exceptionThread->id]
                                );
                            },
                        ],
                    ],
                ]) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Machine Info</strong></div>
            <div class="card-body p-0">
                <?= DetailView::widget([
                    'model'      => $model,
                    'options'    => ['class' => 'table table-sm table-striped mb-0'],
                    'attributes' => [
                        ['attribute' => 'os_name_reg', 'label' => 'OS (Registry)'],
                        ['attribute' => 'os_ver_mdmp', 'label' => 'OS (Minidump)'],
                        [
                            'attribute' => 'os_is_64bit',
                            'label'     => 'OS Bittness',
                            'value'     => $model->getOsBittnessStr(),
                        ],
                        'product_type',
                        ['attribute' => 'cpu_architecture', 'label' => 'CPU Architecture'],
                        ['attribute' => 'cpu_count',        'label' => 'CPU Count'],
                    ],
                ]) ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Resource Usage</strong></div>
            <div class="card-body p-0">
                <?= DetailView::widget([
                    'model'      => $model,
                    'options'    => ['class' => 'table table-sm table-striped mb-0'],
                    'attributes' => [
                        [
                            'attribute' => 'memory_usage_kbytes',
                            'label'     => 'Memory Usage',
                            'value'     => $model->memory_usage_kbytes !== null
                                ? MiscHelpers::fileSizeToStr((int) $model->memory_usage_kbytes * 1024)
                                : '',
                        ],
                        'open_handle_count',
                        'gui_resource_count',
                    ],
                ]) ?>
            </div>
        </div>
    </div>
</div>
