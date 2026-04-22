<?php

/** @var yii\web\View $this */
/** @var int $daemonRetCode */
/** @var array $list */

use yii\helpers\Html;

?>
<div id="daemon_status" class="p-3 border rounded bg-light">
    <p>RetCode: <?= $daemonRetCode ?></p>
    <ul>
        <?php foreach ($list as $item): ?>
            <li><?= Html::encode($item) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
