<?php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\widgets\Menu;

class MainMenu extends Widget
{
    public $activeItem;

    public function run()
    {
        echo '<div class="portlet">';
        echo '<div class="portlet-decoration"><div class="portlet-title">Main Menu</div></div>';
        echo '<div class="portlet-content">';
        
        echo Menu::widget([
            'activateItems' => false,
            'options' => ['class' => 'menu'],
            'items' => [
                [
                    'label' => 'Digest',
                    'url' => ['site/index'],
                    'active' => $this->activeItem == 'Digest'
                ],
                [
                    'label' => 'Crash Reports',
                    'url' => ['crash-report/index'],
                    'active' => $this->activeItem == 'CrashReports',
                    'visible' => Yii::$app->user->can('pperm_browse_some_crash_reports')
                ],
                [
                    'label' => 'Collections',
                    'url' => ['crash-group/index'],
                    'active' => $this->activeItem == 'CrashGroups',
                    'visible' => Yii::$app->user->can('pperm_browse_some_crash_reports')
                ],
                [
                    'label' => 'Bugs',
                    'url' => ['bug/index'],
                    'active' => $this->activeItem == 'Bugs',
                    'visible' => Yii::$app->user->can('pperm_browse_some_bugs')
                ],
                [
                    'label' => 'Debug Info',
                    'url' => ['debug-info/index'],
                    'active' => $this->activeItem == 'DebugInfo',
                    'visible' => Yii::$app->user->can('pperm_browse_some_debug_info')
                ],
                [
                    'label' => 'Administer',
                    'url' => ['site/admin'],
                    'active' => $this->activeItem == 'Administer',
                    'visible' => Yii::$app->user->can('gperm_access_admin_panel')
                ],
                [
                    'label' => 'Logout',
                    'url' => ['site/logout'],
                    'template' => '<a href="{url}" data-method="post" class="logout-menu-item">{label}</a>',
                    'visible' => !Yii::$app->user->isGuest
                ],
            ],
        ]);
        
        echo '</div>';
        echo '</div>';
    }
}
