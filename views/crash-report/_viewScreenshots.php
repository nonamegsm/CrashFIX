<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\helpers\Html;
use yii\helpers\Url;

$dataProvider = $model->searchScreenshots();
$items = $dataProvider->getModels();
$count = 0;
?>
<div class="crash-report-screenshots row g-3">

<?php foreach ($items as $fileItem):
    if (!preg_match('/screenshot[0-9]{1,3}\.(jpg|png)$/i', $fileItem->filename)) {
        continue;
    }
    $count++;
    $fullUrl  = Url::to(['view-screenshot',           'name' => $fileItem->filename, 'rpt' => $model->id]);
    $thumbUrl = Url::to(['view-screenshot-thumbnail', 'name' => $fileItem->filename, 'rpt' => $model->id]);
?>
    <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100 shadow-sm">
            <a target="_blank" rel="noopener" href="<?= $fullUrl ?>">
                <img src="<?= $thumbUrl ?>"
                     alt="<?= Html::encode($fileItem->filename) ?>"
                     class="card-img-top" style="object-fit: cover; max-height: 200px;">
            </a>
            <div class="card-body p-2 text-center small">
                <?= Html::a(Html::encode($fileItem->filename), $fullUrl, ['target' => '_blank', 'rel' => 'noopener']) ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

</div>

<?php if ($count === 0): ?>
    <div class="text-muted fst-italic mt-3">There are no screenshots available.</div>
<?php endif; ?>
