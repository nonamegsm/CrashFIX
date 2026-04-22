<?php

/** @var yii\web\View $this */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Admin - CrashFix';
$this->params['breadcrumbs'][] = 'Administer';
$this->params['breadcrumbs'][] = 'General';

// Set context properties for layouts/column2
$this->context->sidebarActiveItem = 'Administer';
$this->context->adminMenuItem = 'General';
?>

<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">License Information</h5>
    </div>
    <div class="card-body">
        <div id="license_info" class="loading">
            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
            <i class="ms-2">Querying license information...</i>
        </div>
    </div>
</div>

<?php 
$url = Url::to(['site/admin']);
$this->registerJs(<<<JS
    $.ajax({		
        url: "$url",
        type: 'GET',
        data: {'ajax': 1}
    }).done(function( msg ) {
        $("#license_info").replaceWith(msg);
    });
JS
);
?>
