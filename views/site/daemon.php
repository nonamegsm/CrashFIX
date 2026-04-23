<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::$app->name . ' - Daemon Status';

// Sidebar marker for the AdminLTE menu (matches what other admin
// pages set; the layout uses these to highlight the active item).
$this->context->sidebarActiveItem = 'Administer';
$this->context->adminMenuItem     = 'Daemon';
?>

<style>
.cf-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                 gap: 12px; margin: 14px 0 18px 0; }
.cf-stat-card  { padding: 12px 14px; border: 1px solid #d4d4d4; border-radius: 6px;
                 background: #fafafa; }
.cf-stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;
                 color: #777; margin-bottom: 4px; }
.cf-stat-value { font-size: 22px; font-weight: 600; color: #222; line-height: 1.1; }
.cf-stat-sub   { font-size: 11px; color: #666; margin-top: 4px; }
.cf-stat-card.ok      { border-left: 4px solid #5cb85c; }
.cf-stat-card.warn    { border-left: 4px solid #f0ad4e; }
.cf-stat-card.danger  { border-left: 4px solid #d9534f; }
.cf-stat-card.info    { border-left: 4px solid #5bc0de; }
.cf-stat-card.muted   { border-left: 4px solid #bbb; }

.cf-section-head { font-weight: 600; margin: 18px 0 6px 0; color: #444; }
.cf-table        { width: 100%; border-collapse: collapse; font-size: 12px;
                   margin-bottom: 10px; }
.cf-table th, .cf-table td { padding: 5px 8px; border-bottom: 1px solid #eee;
                             text-align: left; }
.cf-table th     { background: #f4f4f4; font-weight: 600; color: #333; }
.cf-table tbody tr:hover { background: #f9f9f9; }
.cf-pill         { display: inline-block; padding: 1px 7px; border-radius: 9px;
                   font-size: 10px; font-weight: 600; text-transform: uppercase; }
.cf-pill.ok      { background: #dff0d8; color: #2c662d; }
.cf-pill.fail    { background: #f2dede; color: #952422; }
.cf-pill.run     { background: #d9edf7; color: #1f6985; }
.cf-elapsed-warn { color: #b04a00; font-weight: 600; }
.cf-meta         { font-size: 11px; color: #888; margin: 4px 0 14px 0; }
.cf-empty        { color: #888; font-style: italic; padding: 10px 0; }
</style>

<h4>Daemon Status:</h4>
<div id="daemon_status" class="loading border p-3 rounded mb-3">
    <i>Querying daemon status&hellip;</i>
</div>

<h4>Live Runtime Statistics
    <span class="cf-meta" style="float:right; font-weight:normal;">
        last update <span id="cf-stats-last">--</span>
    </span>
</h4>

<div id="cf-stats-summary" class="cf-stats-grid">
    <div class="cf-stat-card muted">
        <div class="cf-stat-label">Loading</div>
        <div class="cf-stat-value">--</div>
        <div class="cf-stat-sub">querying database&hellip;</div>
    </div>
</div>

<div class="cf-section-head">Operations in progress
    <span style="font-weight:normal; color:#888;">(<span id="cf-running-count">0</span>)</span>
</div>
<div id="cf-running-wrap">
    <div class="cf-empty">no operations running right now.</div>
</div>

<div class="cf-section-head">By operation type (last hour)</div>
<div id="cf-bytype-wrap">
    <div class="cf-empty">no activity in the last hour.</div>
</div>

<div class="cf-section-head">Recent failures</div>
<div id="cf-failures-wrap">
    <div class="cf-empty">no recent failures.</div>
</div>

<h4 class="mt-4">Recent Operations:</h4>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        ['attribute' => 'id', 'contentOptions' => ['style' => 'width:60px']],
        [
            'label' => 'Operation',
            'value' => function ($d) {
                if (method_exists($d, 'getOperationDescStr')) return $d->getOperationDescStr();
                $name = match ((int) $d->optype) {
                    1 => 'Import PDB',
                    2 => 'Process crash report',
                    3 => 'Delete debug info',
                    default => 'opcode ' . (int) $d->optype,
                };
                $file = $d->operand1 ? basename((string) $d->operand1) : '';
                return $name . ($file ? ' ' . $file : '');
            },
        ],
        [
            'attribute' => 'status',
            'value' => function ($d) {
                return match ((int) $d->status) {
                    1 => 'Started',
                    2 => 'Succeeded',
                    3 => 'Failed',
                    default => 'status ' . (int) $d->status,
                };
            },
            'contentOptions' => function ($d) {
                $s = (int) $d->status;
                if ($s === 3) return ['class' => 'text-danger'];
                if ($s === 1) return ['class' => 'text-info'];
                return [];
            },
        ],
        [
            'attribute' => 'timestamp',
            'value' => fn($d) => date('d/m/y H:i', (int) $d->timestamp),
            'contentOptions' => ['style' => 'width:130px; white-space:nowrap'],
        ],
    ],
]) ?>

<?php
$statusUrl = Url::to(['/site/daemon-status']);
$statsUrl  = Url::to(['/site/daemon-runtime-stats']);

$this->registerJs(<<<JS
// One-shot daemon status pull (existing behaviour)
$.ajax({ url: "$statusUrl" }).done(function (msg) {
    $("#daemon_status").replaceWith(msg);
});

// Live runtime statistics. Polls every 5s.
(function () {
    var STATS_URL = "$statsUrl";
    var POLL_MS   = 5000;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function fmtElapsed(s) {
        s = Math.max(0, parseInt(s, 10));
        if (s < 60) return s + 's';
        if (s < 3600) return Math.floor(s/60) + 'm ' + (s%60) + 's';
        return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm';
    }
    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function nowStr() {
        var d = new Date();
        return pad2(d.getHours())+':'+pad2(d.getMinutes())+':'+pad2(d.getSeconds());
    }
    function fmtPct(p) {
        return (p === null || p === undefined) ? 'n/a' : (p + '%');
    }
    function fmtNum(n) {
        return (n === null || n === undefined) ? '0'
            : String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function classifySuccess(pct) {
        if (pct === null || pct === undefined) return 'muted';
        if (pct >= 95) return 'ok';
        if (pct >= 80) return 'warn';
        return 'danger';
    }

    function renderSummary(d) {
        var t  = d.throughput;
        var lh = d.last_hour;
        var cl = d.crash_lifetime || {};
        var sclass = classifySuccess(lh.success_pct);
        var pclass = classifySuccess(cl.processed_pct);
        var pctText = fmtPct(lh.success_pct);
        var processedPctText = fmtPct(cl.processed_pct);
        return [
            '<div class="cf-stat-card info">',
            '  <div class="cf-stat-label">In flight now</div>',
            '  <div class="cf-stat-value">' + lh.in_flight + '</div>',
            '  <div class="cf-stat-sub">operations being processed</div>',
            '</div>',
            '<div class="cf-stat-card ok">',
            '  <div class="cf-stat-label">Last 5 min</div>',
            '  <div class="cf-stat-value">' + t.per_5m + '</div>',
            '  <div class="cf-stat-sub">ops dispatched</div>',
            '</div>',
            '<div class="cf-stat-card ok">',
            '  <div class="cf-stat-label">Last 15 min</div>',
            '  <div class="cf-stat-value">' + t.per_15m + '</div>',
            '  <div class="cf-stat-sub">ops dispatched</div>',
            '</div>',
            '<div class="cf-stat-card ok">',
            '  <div class="cf-stat-label">Last 60 min</div>',
            '  <div class="cf-stat-value">' + t.per_60m + '</div>',
            '  <div class="cf-stat-sub">~' + t.rate_per_min + ' / min</div>',
            '</div>',
            '<div class="cf-stat-card ' + sclass + '">',
            '  <div class="cf-stat-label">Success rate (1h)</div>',
            '  <div class="cf-stat-value">' + pctText + '</div>',
            '  <div class="cf-stat-sub">' + lh.succeeded + ' ok / ' + lh.failed + ' failed</div>',
            '</div>',
            '<div class="cf-stat-card ' + pclass + '">',
            '  <div class="cf-stat-label">Processed (lifetime)</div>',
            '  <div class="cf-stat-value">' + processedPctText + '</div>',
            '  <div class="cf-stat-sub">' + fmtNum(cl.processed) + ' of ' + fmtNum(cl.total) +
                ' (' + fmtNum(cl.pending) + ' pending)</div>',
            '</div>'
        ].join('');
    }

    function renderRunning(items) {
        if (!items || !items.length)
            return '<div class="cf-empty">no operations running right now.</div>';
        var out = ['<table class="cf-table">',
            '<thead><tr><th>cmd id</th><th>type</th><th>file</th><th>elapsed</th></tr></thead>',
            '<tbody>'];
        items.forEach(function (it) {
            var elapsedClass = it.elapsed_s > 60 ? 'cf-elapsed-warn' : '';
            out.push('<tr>',
                '<td>'+escapeHtml(it.cmdid)+'</td>',
                '<td><span class="cf-pill run">'+escapeHtml(it.optype)+'</span></td>',
                '<td>'+escapeHtml(it.file)+'</td>',
                '<td class="'+elapsedClass+'">'+fmtElapsed(it.elapsed_s)+'</td>',
                '</tr>');
        });
        out.push('</tbody></table>');
        return out.join('');
    }

    function renderByType(byType) {
        var anyActivity = false;
        var rows = [];
        Object.keys(byType).forEach(function (name) {
            var b = byType[name];
            if (b.total > 0) anyActivity = true;
            var pct = (b.ok + b.failed) > 0 ? Math.round(100 * b.ok / (b.ok + b.failed)) : null;
            var pctTxt = pct === null ? '\u2014' : pct + '%';
            rows.push('<tr>',
                '<td>'+escapeHtml(name)+'</td>',
                '<td>'+b.total+'</td><td>'+b.ok+'</td>',
                '<td>'+b.failed+'</td><td>'+b.started+'</td>',
                '<td>'+pctTxt+'</td>',
                '</tr>');
        });
        if (!anyActivity)
            return '<div class="cf-empty">no activity in the last hour.</div>';
        return '<table class="cf-table">'+
            '<thead><tr><th>operation</th><th>total</th><th>ok</th>'+
            '<th>failed</th><th>started</th><th>success %</th></tr></thead>'+
            '<tbody>'+rows.join('')+'</tbody></table>';
    }

    function renderFailures(items) {
        if (!items || !items.length)
            return '<div class="cf-empty">no recent failures.</div>';
        var out = ['<table class="cf-table">',
            '<thead><tr><th>when</th><th>type</th><th>file</th></tr></thead>',
            '<tbody>'];
        items.forEach(function (it) {
            out.push('<tr>',
                '<td>'+escapeHtml(it.when)+' <span style="color:#888">('+fmtElapsed(it.ago_s)+' ago)</span></td>',
                '<td><span class="cf-pill fail">'+escapeHtml(it.optype)+'</span></td>',
                '<td>'+escapeHtml(it.file)+'</td>',
                '</tr>');
        });
        out.push('</tbody></table>');
        return out.join('');
    }

    function refresh() {
        $.ajax({ url: STATS_URL, dataType: 'json', cache: false })
         .done(function (data) {
            $('#cf-stats-summary').html(renderSummary(data));
            $('#cf-running-wrap').html(renderRunning(data.running));
            $('#cf-running-count').text(data.running_count);
            $('#cf-bytype-wrap').html(renderByType(data.by_type));
            $('#cf-failures-wrap').html(renderFailures(data.recent_failures));
            $('#cf-stats-last').text(nowStr());
         })
         .fail(function () {
            $('#cf-stats-last').text(nowStr() + ' (request failed)');
         });
    }
    refresh();
    setInterval(refresh, POLL_MS);
})();
JS
);
?>
