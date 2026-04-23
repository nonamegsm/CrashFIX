<?php

$this->pageTitle=Yii::app()->name . ' - Browse Daemon Status';

$this->breadcrumbs=array(
	'Administer', 
	'Daemon',
);

?>

<style>
/* Lightweight styling for the runtime-stats panels. Kept inline so the
   page is self-contained and works with the legacy YAML grid framework. */
.cf-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

<div class="subheader">Daemon Status:</div>

<div class="span-18 last">
<div id="daemon_status" class="flash-success">
    <ul class="daemon-status-list">
        <li class="daemon-status-list-item">
            Querying daemon status...
        </li>
    </ul>
</div>
</div>

<div class="span-18 last">
<div class="subheader">Live Runtime Statistics
    <span class="cf-meta" style="float:right; font-weight:normal;">
        last update <span id="cf-stats-last">--</span>
    </span>
</div>

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

</div>

<div class="span-18 last">
<div class="subheader">Recent Daemon Operations:</div>

<!-- Grid view -->
<?php $this->widget('zii.widgets.grid.CGridView', array(
      'dataProvider'=>$dataProvider,
	  'selectableRows'=>null,
      'columns'=>array(		            		  
		  array(  
			'header'=>'Operation',
            'name'=>'optype',            
            'value'=>'$data->getOperationDescStr()',
		  ),
		  array(                          
			  'header'=>'Status',
			  'name'=>'status',
			  'value'=>'Lookup::item(\'OperationStatus\', $data->status)',			  
          ),		  
		  array(  
            'name'=>'timestamp',
            'type'=>'text',
            'value'=>'date("d/m/y H:i", $data->timestamp)',
		  ),		  
      ),
 )); 
  
 ?>
</div>

<?php 
 
 $statusUrl = Yii::app()->createAbsoluteUrl('site/daemonStatus');
 $statsUrl  = Yii::app()->createAbsoluteUrl('site/daemonRuntimeStats');

 $script = <<<SCRIPT

// One-shot daemon-status pull (existing behaviour)
$.ajax({
    url: "$statusUrl",
    data: ""
}).done(function (msg) {
    $("#daemon_status").replaceWith(msg);
});

// Live runtime statistics. Polls the new daemonRuntimeStats action,
// renders summary cards + a per-operation in-flight strip, and
// repeats every 5 seconds.
(function () {
    var STATS_URL = "$statsUrl";
    var POLL_MS   = 5000;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }

    function fmtElapsed(s) {
        s = Math.max(0, parseInt(s, 10));
        if (s < 60)    return s + 's';
        if (s < 3600)  return Math.floor(s/60) + 'm ' + (s%60) + 's';
        return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm';
    }

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function nowStr() {
        var d = new Date();
        return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
    }

    function classifySuccess(pct) {
        if (pct === null || pct === undefined) return 'muted';
        if (pct >= 95) return 'ok';
        if (pct >= 80) return 'warn';
        return 'danger';
    }

    function fmtPct(p) {
        // Render a number as a percentage with one decimal, or "n/a"
        // when the underlying total was zero (so we don't lie with 0%).
        return (p === null || p === undefined) ? 'n/a' : (p + '%');
    }

    function fmtNum(n) {
        // Thousands separator for the lifetime totals - "12,456 of
        // 13,229 done" reads better than "12456 of 13229 done".
        return (n === null || n === undefined) ? '0'
            : String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function renderSummary(d) {
        var t  = d.throughput;
        var lh = d.last_hour;
        var cl = d.crash_lifetime || {};
        var sclass = classifySuccess(lh.success_pct);
        var pclass = classifySuccess(cl.processed_pct);
        var pctText = fmtPct(lh.success_pct);
        var processedPctText = fmtPct(cl.processed_pct);
        var inFlightClass = lh.in_flight > 0 ? 'info' : 'muted';

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
            // Lifetime processing progress. Counts every crash report
            // ever uploaded; "Processed" = STATUS_PROCESSED (3).
            // Subline shows absolute counts and the "still pending"
            // remainder so admins can tell at a glance if the daemon
            // is keeping up with intake.
            '<div class="cf-stat-card ' + pclass + '">',
            '  <div class="cf-stat-label">Processed (lifetime)</div>',
            '  <div class="cf-stat-value">' + processedPctText + '</div>',
            '  <div class="cf-stat-sub">' + fmtNum(cl.processed) + ' of ' + fmtNum(cl.total) +
                  ' (<span title="reports not yet in a terminal state">' + fmtNum(cl.pending) + ' pending</span>)</div>',
            '</div>'
        ].join('');
    }

    function renderRunning(items) {
        if (!items || !items.length)
            return '<div class="cf-empty">no operations running right now.</div>';
        var out = ['<table class="cf-table">',
            '<thead><tr><th>cmd id</th><th>type</th><th>file</th><th>elapsed</th></tr></thead>',
            '<tbody>'];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            var elapsedClass = it.elapsed_s > 60 ? 'cf-elapsed-warn' : '';
            out.push('<tr>');
            out.push('<td>' + escapeHtml(it.cmdid) + '</td>');
            out.push('<td><span class="cf-pill run">' + escapeHtml(it.optype) + '</span></td>');
            out.push('<td>' + escapeHtml(it.file) + '</td>');
            out.push('<td class="' + elapsedClass + '">' + fmtElapsed(it.elapsed_s) + '</td>');
            out.push('</tr>');
        }
        out.push('</tbody></table>');
        return out.join('');
    }

    function renderByType(byType) {
        var anyActivity = false;
        var rows = [];
        for (var name in byType) {
            if (!byType.hasOwnProperty(name)) continue;
            var b = byType[name];
            if (b.total > 0) anyActivity = true;
            var pct = (b.ok + b.failed) > 0
                ? Math.round(100 * b.ok / (b.ok + b.failed))
                : null;
            var pctTxt = pct === null ? '\u2014' : pct + '%';
            rows.push(
                '<tr>' +
                '<td>' + escapeHtml(name) + '</td>' +
                '<td>' + b.total + '</td>' +
                '<td>' + b.ok + '</td>' +
                '<td>' + b.failed + '</td>' +
                '<td>' + b.started + '</td>' +
                '<td>' + pctTxt + '</td>' +
                '</tr>'
            );
        }
        if (!anyActivity)
            return '<div class="cf-empty">no activity in the last hour.</div>';
        return '<table class="cf-table">' +
            '<thead><tr><th>operation</th><th>total</th><th>ok</th>' +
            '<th>failed</th><th>started</th><th>success %</th></tr></thead>' +
            '<tbody>' + rows.join('') + '</tbody></table>';
    }

    function renderFailures(items) {
        if (!items || !items.length)
            return '<div class="cf-empty">no recent failures.</div>';
        var out = ['<table class="cf-table">',
            '<thead><tr><th>when</th><th>type</th><th>file</th></tr></thead>',
            '<tbody>'];
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            out.push('<tr>');
            out.push('<td>' + escapeHtml(it.when) + ' <span style="color:#888">(' + fmtElapsed(it.ago_s) + ' ago)</span></td>');
            out.push('<td><span class="cf-pill fail">' + escapeHtml(it.optype) + '</span></td>');
            out.push('<td>' + escapeHtml(it.file) + '</td>');
            out.push('</tr>');
        }
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

SCRIPT;
 
 Yii::app()->getClientScript()->registerScript("DebugInfo", $script, CClientScript::POS_READY); ?>