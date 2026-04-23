<?php

/** @var yii\web\View $this */

use yii\bootstrap4\Alert;
use yii\helpers\Html;

$this->title = 'Yii1 migration export - CrashFix';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'Migration export';

$this->context->sidebarActiveItem = 'Administer';
$this->context->adminMenuItem = 'Migration export';
?>

<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Database SQL dump</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Produces a full <strong>mysqldump</strong> of the database this site is using now
            (the same schema Yii1 CrashFix used if you have not migrated yet). Use the file to
            seed a server for the Yii2 installer’s <em>Existing CrashFix (Yii1)</em> mode, or
            keep it as an offline backup. Copy the <code>protected/data</code> tree separately.
        </p>
        <p class="text-muted small">
            The dump includes routines, triggers, and events. Large databases may take several
            minutes; keep this page open until the download starts.
        </p>
        <div class="alert alert-warning small mb-4">
            The file contains a complete copy of your database (including user accounts and tokens).
            Store it securely and do not expose it over HTTP.
        </div>

        <?php if (Yii::$app->session->hasFlash('error')): ?>
            <?= Alert::widget([
                'options' => ['class' => 'alert-danger'],
                'body' => Html::encode(Yii::$app->session->getFlash('error')),
            ]) ?>
        <?php endif; ?>

        <?= Html::beginForm(['migration-export-download'], 'post', ['class' => 'd-inline']) ?>
        <?= Html::submitButton('Download .sql dump', [
            'class' => 'btn btn-primary',
            'data' => ['confirm' => 'Download a full database dump now?'],
        ]) ?>
        <?= Html::endForm() ?>

        <p class="small text-muted mt-4 mb-0">
            Requires the <code>mysqldump</code> client on the server. On Windows/XAMPP, set
            <code>mysqldumpPath</code> in <code>config/params.php</code> if the download fails
            (e.g. <code>C:\\xampp\\mysql\\bin\\mysqldump.exe</code>).
        </p>
    </div>
</div>
