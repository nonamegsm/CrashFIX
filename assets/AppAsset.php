<?php

namespace app\assets;

use yii\web\AssetBundle;

class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
    ];
    public $js = [
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'hail812\adminlte3\assets\AdminLteAsset',
        'hail812\adminlte3\assets\FontAwesomeAsset',
    ];
}
