<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= \yii\helpers\Url::home() ?>" class="brand-link">
        <img src="<?=$assetDir?>/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light">CrashFix</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <?php if (!Yii::$app->user->isGuest): ?>
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <div class="img-circle elevation-2 d-flex justify-content-center align-items-center bg-primary" style="width: 34px; height: 34px; color: white;">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            <div class="info">
                <a href="<?= \yii\helpers\Url::to(['/user/view', 'id' => Yii::$app->user->id]) ?>" class="d-block"><?= \yii\helpers\Html::encode(Yii::$app->user->identity->username) ?></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <?php
            echo \hail812\adminlte\widgets\Menu::widget([
                'items' => [
                    ['label' => 'MAIN MENU', 'header' => true],
                    ['label' => 'Digest', 'url' => ['/site/index'], 'icon' => 'tachometer-alt'],
                    [
                        'label' => 'Crash Reports',
                        'url' => ['/crash-report/index'],
                        'icon' => 'file-medical-alt',
                        'visible' => Yii::$app->user->can('pperm_browse_some_crash_reports')
                    ],
                    [
                        'label' => 'Collections',
                        'url' => ['/crash-group/index'],
                        'icon' => 'folder-open',
                        'visible' => Yii::$app->user->can('pperm_browse_some_crash_reports')
                    ],
                    [
                        'label' => 'Bugs',
                        'url' => ['/bug/index'],
                        'icon' => 'bug',
                        'visible' => Yii::$app->user->can('pperm_browse_some_bugs')
                    ],
                    [
                        'label' => 'Debug Info',
                        'url' => ['/debug-info/index'],
                        'icon' => 'info-circle',
                        'visible' => Yii::$app->user->can('pperm_browse_some_debug_info')
                    ],
                    [
                        'label' => 'Failed Items',
                        'url' => ['/site/failed'],
                        'icon' => 'exclamation-triangle',
                        // Visible to anyone with at least one of the relevant
                        // browse permissions; the page itself gates each
                        // section individually.
                        'visible' =>
                               Yii::$app->user->can('pperm_browse_some_crash_reports')
                            || Yii::$app->user->can('pperm_browse_some_debug_info'),
                    ],
                    [
                        'label' => 'Serials Info',
                        'url' => ['/serials-info/index'],
                        'icon' => 'barcode',
                        // Same gate as the Yii1 sidebar entry: anyone who
                        // can browse some crash reports can see the
                        // (read-only) box / card serial aggregation.
                        'visible' => Yii::$app->user->can('pperm_browse_some_crash_reports'),
                    ],
                    [
                        'label' => 'Extra Files',
                        'url' => ['/extra-files/index'],
                        'icon' => 'file-archive',
                        'visible' => Yii::$app->user->can('gperm_access_admin_panel'),
                    ],

                    ['label' => 'ADMINISTRATION', 'header' => true, 'visible' => Yii::$app->user->can('gperm_access_admin_panel')],
                    [
                        'label' => 'Administer',
                        'url' => ['/site/admin'],
                        'icon' => 'cogs',
                        'visible' => Yii::$app->user->can('gperm_access_admin_panel'),
                        'items' => [
                            ['label' => 'General', 'url' => ['/site/admin'], 'icon' => 'tools'],
                            ['label' => 'Users', 'url' => ['/user/index'], 'icon' => 'users'],
                            ['label' => 'Groups', 'url' => ['/user-group/index'], 'icon' => 'user-friends'],
                            ['label' => 'Projects', 'url' => ['/project/index'], 'icon' => 'project-diagram'],
                            ['label' => 'Daemon', 'url' => ['/site/daemon'], 'icon' => 'server'],
                            ['label' => 'Mail', 'url' => ['/mail/index'], 'icon' => 'envelope'],
                            ['label' => 'Yii1 migration export', 'url' => ['/site/migration-export'], 'icon' => 'database'],
                        ]
                    ],
                ],
            ]);
            ?>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
