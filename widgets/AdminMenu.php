<?php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\widgets\Menu;

class AdminMenu extends Widget
{
    public $activeItem;

    public function run()
    {
        echo '<div class="portlet">';
        echo '<div class="portlet-content">';
        
        echo Menu::widget([
            'activateItems' => false,
            'options' => ['class' => 'menu'],
            'items' => [
                ['label' => 'General', 'url' => ['site/admin'], 'active' => $this->activeItem == 'General'],
                ['label' => 'Users', 'url' => ['user/index'], 'active' => $this->activeItem == 'Users'],
                ['label' => 'Groups', 'url' => ['user-group/index'], 'active' => $this->activeItem == 'Groups'],
                ['label' => 'Projects', 'url' => ['project/index'], 'active' => $this->activeItem == 'Projects'],
                ['label' => 'Daemon', 'url' => ['site/daemon'], 'active' => $this->activeItem == 'Daemon'],
                ['label' => 'Mail', 'url' => ['mail/index'], 'active' => $this->activeItem == 'Mail'],
            ],
        ]);
        
        echo '</div>';
        echo '</div>';
    }
}
