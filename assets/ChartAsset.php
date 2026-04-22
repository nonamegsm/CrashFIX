<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

/**
 * Asset bundle pulling Chart.js from a CDN. Kept as a CDN reference
 * (rather than vendored under web/) so the dependency stays out of git
 * and gets updated by simply bumping {@see $version}.
 *
 * Loaded by views that draw a `<canvas>` chart.
 */
class ChartAsset extends AssetBundle
{
    public $sourcePath = null;

    public string $version = '4.4.4';

    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];

    public function init()
    {
        parent::init();
        // Pinned via SRI-friendly versioned URL.
        $this->js[] = "https://cdn.jsdelivr.net/npm/chart.js@{$this->version}/dist/chart.umd.min.js";
        $this->js[] = "https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js";
    }
}
