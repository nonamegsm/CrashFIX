<?php
/** @var yii\web\View $this */
/** @var app\models\Debuginfo $model */
/** @var bool $alreadyExists */

/**
 * Plain-text response consumed by the desktop CrashSender uploader.
 *
 * The legacy protocol expects a small, well-defined block of key/value
 * lines. We must NOT render the AdminLTE chrome here.
 */

$this->beginContent('@app/views/layouts/install.php'); // minimal layout
?>
<?php
$layoutLines = [];
if ($model->hasErrors()) {
    $layoutLines[] = 'status=error';
    foreach ($model->getFirstErrors() as $attr => $err) {
        $layoutLines[] = "error[{$attr}]={$err}";
    }
} elseif (!empty($alreadyExists)) {
    $layoutLines[] = 'status=already_uploaded';
    $layoutLines[] = 'guid=' . $model->guid;
} elseif (!empty($model->id)) {
    $layoutLines[] = 'status=ok';
    $layoutLines[] = 'id=' . $model->id;
    $layoutLines[] = 'guid=' . $model->guid;
} else {
    $layoutLines[] = 'status=pending';
    $layoutLines[] = 'guid=' . $model->guid;
}
?>
<pre style="font-family: monospace; white-space: pre-wrap;"><?= htmlspecialchars(implode("\n", $layoutLines), ENT_QUOTES, 'UTF-8') ?></pre>
<?php $this->endContent(); ?>
