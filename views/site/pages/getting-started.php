<?php
/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Getting started';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card card-outline card-secondary">
    <div class="card-body">
        <p class="mb-2">
            This is a sample <strong>static page</strong>. Add more PHP views under
            <code>views/site/pages/</code> and open them as:
        </p>
        <ul>
            <li><?= Html::encode(Yii::$app->request->hostInfo . Url::to(['site/page', 'view' => 'getting-started'])) ?></li>
            <li>or the pretty path <code>/site/page/getting-started</code> when URL rewriting is enabled.</li>
        </ul>
        <p class="text-muted small mb-0">
            Remove or replace this file in production; it exists so the Yii1
            <code>site/page?view=…</code> behaviour has a working example.
        </p>
    </div>
</div>
