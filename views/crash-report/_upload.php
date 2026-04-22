<?php
/** @var yii\web\View $this */
/** @var app\models\CrashReport $model */

if ($model->hasErrors()) {
    echo "ERROR: " . implode(", ", $model->getErrorSummary(true));
} else {
    echo "SUCCESS: Crash report " . $model->id . " uploaded.";
}
