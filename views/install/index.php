<?php
/** @var yii\web\View $this */
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Welcome - CrashFix Setup';
?>

<div class="install-index text-center my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-body p-5">
            <h2 class="card-title mb-4">Welcome to CrashFix</h2>
            <p class="card-text lead text-muted">This wizard will guide you through the process of setting up your CrashFix application.</p>
            <div class="mt-5">
                <?= Html::a('Start Setup', ['requirements'], ['class' => 'btn btn-primary btn-lg px-5']) ?>
            </div>
        </div>
    </div>
</div>
