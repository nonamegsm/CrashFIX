<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\bootstrap4\ActiveForm;
use yii\web\JsExpression;
use app\assets\ChartAsset;
use app\components\MiscHelpers;

ChartAsset::register($this);

$this->title = Yii::$app->name;
$this->params['breadcrumbs'][] = 'Digest';

$user = Yii::$app->user;
$myProjects = $user->getMyProjects();

$curProjectId = $user->getCurProjectId();
?>

<?php if (count($myProjects) == 0): ?>
    <div class="alert alert-info">
        You have no projects assigned.
    </div>
<?php else: ?>

    <!-- Project / Version selection -->
    <div class="card mb-4 border-0 shadow-sm" id="div_proj_selection">
        <div class="card-body py-2 px-3">
            <?php $form = ActiveForm::begin([
                'action' => ['site/set-cur-project'],
                'method' => 'get',
                'id' => 'proj_form',
                'options' => ['class' => 'form-row align-items-center'],
            ]); ?>
            <div class="col-auto">
                <span class="text-muted fw-500 small">Project:</span>
            </div>
            <div class="col-auto">
                <?= Html::dropDownList('proj', $user->getCurProjectId(),
                    ArrayHelper::map($myProjects, 'id', 'name'),
                    ['id' => 'proj', 'class' => 'form-control form-control-sm border-0 bg-light']) ?>
            </div>
            <div class="col-auto ps-3">
                <span class="text-muted fw-500 small">Version:</span>
            </div>
            <div class="col-auto">
                <?php
                $selVer = -1;
                $versions = $user->getCurProjectVersions($selVer);
                echo Html::dropDownList('ver', $selVer, $versions, ['id' => 'ver', 'class' => 'form-control form-control-sm border-0 bg-light']);
                ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <!-- Crash Reports Section -->
    <?php if ($user->can('pperm_browse_crash_reports', ['project_id' => $curProjectId])): ?>
    <div class="row mt-3">

        <!-- Crash Report Uploads Chart -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header" id="header_crash_report_uploads" title="Displays how many crash report files were uploaded recently and their upload dynamics">
                    Crash Report Uploads
                    <div class="float-end">
                        <a id="link_week" href="javascript:;" class="btn btn-sm btn-outline-secondary">Week</a>
                        <a id="link_month" href="javascript:;" class="btn btn-sm btn-outline-secondary">Month</a>
                        <a id="link_year" href="javascript:;" class="btn btn-sm btn-outline-secondary">Year</a>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="crashreport-upload-stat" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Crash Report Totals -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header" title="Displays crash reports count for current project and version, or for all versions of current project">
                    Crash Report Totals
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <?php
                        $selVer = $user->getCurProjectVer();
                        if ($selVer != -1):
                        ?>
                            <li>Current version:
                                <ul>
                                    <li>
                                        <?php
                                        $totalFileSize = 0;
                                        $percentOfDiskQuota = 0;
                                        $reportCount = $user->getCurProject()->getCrashReportCount($totalFileSize, $percentOfDiskQuota, $selVer);
                                        echo $reportCount;
                                        ?>
                                        crash reports (<?= MiscHelpers::fileSizeToStr($totalFileSize) ?>)
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <li>Entire project, all versions:
                            <ul>
                                <li>
                                    <?php
                                    $totalFileSize = 0;
                                    $percentOfDiskQuota = 0;
                                    $reportCount = $user->getCurProject()->getCrashReportCount($totalFileSize, $percentOfDiskQuota);
                                    echo $reportCount . ' crash reports (' . MiscHelpers::fileSizeToStr($totalFileSize);
                                    if ($percentOfDiskQuota > 0) echo ', ' . sprintf("%.0f", $percentOfDiskQuota) . '% of disk quota';
                                    echo ')';
                                    ?>
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $percentOfDiskQuota ?>%" aria-valuenow="<?= $percentOfDiskQuota ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Crash Reports per Project Version -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header" title="Displays how many crash reports were uploaded for each version of current project">Crash Reports per Project Version</div>
                <div class="card-body">
                    <canvas id="crashreport-version-distrib" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Geographic Locations -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header" title="Displays from what countries the crash reports were uploaded">Geographic Locations</div>
                <div class="card-body">
                    <canvas id="crashreport-geo-locations" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- OS Version Distribution -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header" title="Displays operating system names where crashes occurred">OS Version Distribution</div>
                <div class="card-body">
                    <canvas id="crashreport-os-version-distrib" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Crash Collections -->
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header" title="Displays top collections containing the majority of crash reports">Top Crash Collections</div>
                <div class="card-body">
                    <?php
                    $curProjectVer = $user->getCurProjectVer();
                    $topCollections = $user->getCurProject()->getTopCrashGroups($curProjectVer);
                    if (count($topCollections) == 0):
                    ?>
                        <ul class="list-unstyled mb-0"><li>No data available</li></ul>
                    <?php else: ?>
                        <ul id="top_collections" class="list-unstyled mb-0">
                            <?php
                            $totalFileSize2 = 0;
                            $percentOfDiskQuota2 = 0;
                            $totalReportCount = $user->getCurProject()->getCrashReportCount($totalFileSize2, $percentOfDiskQuota2, $curProjectVer);
                            foreach ($topCollections as $collection):
                                $percent = sprintf("%.0f", $totalReportCount != 0 ? 100 * $collection->crashReportCount / $totalReportCount : 0);
                            ?>
                                <li><?= $collection->crashReportCount ?> reports (<?= $percent ?>%) in <?= Html::a(Html::encode(MiscHelpers::addEllipsis($collection->title, 110)), ['crash-group/view', 'id' => $collection->id], ['class' => 'top-collection-title']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /crash reports row -->
    <?php endif; ?>

    <!-- Bugs Section -->
    <?php if ($user->can('pperm_browse_bugs', ['project_id' => $curProjectId])): ?>
    <div class="row mt-3">

        <!-- Bug Dynamics Chart -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header" title="Displays how bug statuses changed recently">
                    Bug Dynamics
                    <div class="float-end">
                        <a id="link_bug_week" href="javascript:;" class="btn btn-sm btn-outline-secondary">Week</a>
                        <a id="link_bug_month" href="javascript:;" class="btn btn-sm btn-outline-secondary">Month</a>
                        <a id="link_bug_year" href="javascript:;" class="btn btn-sm btn-outline-secondary">Year</a>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="bug-dynamics-stat" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Bug Status Distribution  -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header" title="Displays how many bugs (in percent) are open or closed">Bug Statuses</div>
                <div class="card-body">
                    <canvas id="bug-status-dist" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Bug Changes -->
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header" title="Displays what bug changes have been performed recently">Recent Bug Changes</div>
                <div class="card-body">
                    <?php
                    $curProjectVer = $user->getCurProjectVer();
                    $recentBugChanges = $user->getCurProject()->getRecentBugChanges($curProjectVer);
                    if (count($recentBugChanges) == 0):
                    ?>
                        <ul class="list-unstyled mb-0"><li>No data available</li></ul>
                    <?php else: ?>
                        <?php foreach ($recentBugChanges as $bugChange): ?>
                            <?= $this->render('_bugChange', ['model' => $bugChange]) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /bugs row -->
    <?php endif; ?>

    <!-- Debug Info Section -->
    <?php if ($user->can('pperm_browse_debug_info', ['project_id' => $curProjectId])): ?>
    <div class="row mt-3">

        <!-- Debug Info Totals -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header" title="Displays how many debug info files were uploaded for this project">Debug Info Totals (All Versions)</div>
                <div class="card-body">
                    <?php
                    $totalFileSize = 0;
                    $percentOfDiskQuota = 0;
                    $fileCount = $user->getCurProject()->getDebugInfoCount($totalFileSize, $percentOfDiskQuota);
                    echo $fileCount . ' files (' . MiscHelpers::fileSizeToStr($totalFileSize);
                    if ($percentOfDiskQuota > 0) echo ', ' . sprintf("%.0f", $percentOfDiskQuota) . '% of disk quota';
                    echo ')';
                    ?>
                    <div class="progress mt-2" style="height: 20px;">
                        <div class="progress-bar" role="progressbar" style="width: <?= $percentOfDiskQuota ?>%" aria-valuenow="<?= $percentOfDiskQuota ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Debug Info Upload Chart -->
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header" title="Displays recent debug info file upload dynamics for all version of this project">Debug Info Uploads (All Versions)</div>
                <div class="card-body">
                    <canvas id="debuginfo-upload-stat" height="200"></canvas>
                </div>
            </div>
        </div>

    </div><!-- /debug info row -->
    <?php endif; ?>

<?php
/**
 * Chart endpoint URLs. The legacy w/h params are no longer required
 * but still accepted server-side for URL backward compatibility.
 */
$endpoints = [
    'crashreport-upload-stat'      => Url::to(['crash-report/upload-stat'], true),
    'crashreport-version-distrib'  => Url::to(['crash-report/version-dist'], true),
    'crashreport-os-version-distrib' => Url::to(['crash-report/os-version-dist'], true),
    'crashreport-geo-locations'    => Url::to(['crash-report/geo-location-dist'], true),
    'bug-dynamics-stat'            => Url::to(['bug/status-dynamics'], true),
    'bug-status-dist'              => Url::to(['bug/status-dist'], true),
    'debuginfo-upload-stat'        => Url::to(['debug-info/upload-stat'], true),
];
$endpointsJson = json_encode($endpoints, JSON_UNESCAPED_SLASHES);

$this->registerJs(<<<JS
(function () {
    var endpoints = $endpointsJson;
    var charts = {};

    /**
     * Render or refresh a Chart.js chart of the given type, fetching its
     * dataset from the matching endpoint with optional query string.
     */
    function loadChart(canvasId, type, params) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || typeof Chart === 'undefined') return;

        var url = endpoints[canvasId];
        if (!url) return;
        var qs  = params ? ('?' + new URLSearchParams(params).toString()) : '';

        fetch(url + qs, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) {
                if (charts[canvasId]) { charts[canvasId].destroy(); }
                charts[canvasId] = new Chart(canvas, {
                    type: type,
                    data: data,
                    options: optionsFor(type)
                });
            })
            .catch(function () {
                var ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#888';
                ctx.font = '14px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('Chart unavailable', canvas.width / 2, canvas.height / 2);
            });
    }

    function optionsFor(type) {
        var common = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: type !== 'bar' }
            }
        };
        if (type === 'bar' || type === 'line') {
            common.scales = {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            };
        }
        return common;
    }

    // Period link handlers (week/month/year switch the trailing window).
    function bindPeriod(prefix, canvasId, type) {
        ['week', 'month', 'year'].forEach(function (k) {
            var btn = document.getElementById(prefix + k);
            if (!btn) return;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                loadChart(canvasId, type, { period: { week: 7, month: 30, year: 365 }[k] });
            });
        });
    }

    // Project/Version selector auto-submit
    var proj = document.getElementById('proj');
    var ver  = document.getElementById('ver');
    if (proj) proj.addEventListener('change', function () { document.getElementById('proj_form').submit(); });
    if (ver)  ver.addEventListener('change',  function () { document.getElementById('proj_form').submit(); });

    bindPeriod('link_',     'crashreport-upload-stat', 'line');
    bindPeriod('link_bug_', 'bug-dynamics-stat',       'line');

    // Initial loads.
    loadChart('crashreport-upload-stat',        'line',     { period: 7 });
    loadChart('crashreport-version-distrib',    'bar');
    loadChart('crashreport-os-version-distrib', 'bar');
    loadChart('crashreport-geo-locations',      'doughnut');
    loadChart('bug-dynamics-stat',              'line',     { period: 7 });
    loadChart('bug-status-dist',                'doughnut');
    loadChart('debuginfo-upload-stat',          'line',     { period: 7 });
})();
JS
);
?>

<?php endif; ?>
