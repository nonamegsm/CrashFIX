<?php

use yii\helpers\Html;

?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?=\yii\helpers\Url::home()?>" class="nav-link">Home</a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <?php if (Yii::$app->user->isGuest): ?>
        <li class="nav-item">
            <a href="<?=\yii\helpers\Url::to(['/site/login'])?>" class="nav-link">Login</a>
        </li>
        <?php else: ?>
        <li class="nav-item">
            <?= Html::a('<i class="fas fa-sign-out-alt"></i> Logout (' . Html::encode(Yii::$app->user->identity->username) . ')', ['/site/logout'], ['data-method' => 'post', 'class' => 'nav-link']) ?>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
    </ul>
</nav>
<!-- /.navbar -->
