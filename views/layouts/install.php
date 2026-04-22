<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use yii\helpers\Html;

AppAsset::register($this);

$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'description', 'content' => $this->params['meta_description'] ?? '']);
$this->registerMetaTag(['name' => 'keywords', 'content' => $this->params['meta_keywords'] ?? '']);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <style>
        body { background-color: #f8f9fa; }
        .setup-container { margin-top: 50px; }
        .setup-header { margin-bottom: 30px; text-align: center; }
        .setup-logo { font-size: 2rem; font-weight: bold; color: #0d6efd; }
    </style>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<main role="main" class="flex-shrink-0">
    <div class="container setup-container">
        <div class="setup-header">
            <div class="setup-logo">CrashFix <span class="text-muted" style="font-weight: normal;">Setup</span></div>
        </div>
        <?= $content ?>
    </div>
</main>

<footer class="footer mt-auto py-3 text-muted">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> CrashFix. All rights reserved.</p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
