<?php

/** @var yii\web\View $this */
/** @var int $retCode */
/** @var string $errorMsg */

use yii\helpers\Html;

?>
<div id="daemon-check" class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Daemon Check Alert (Code <?= $retCode ?>)!</strong> <?= Html::encode($errorMsg) ?>
    <button type="button" id="btn_close" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
