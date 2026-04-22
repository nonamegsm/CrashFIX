<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */

use yii\helpers\Html;
use yii\helpers\Url;

$dataProvider = $model->searchVideos();
$items = $dataProvider->getModels();
$count = 0;
?>
<div class="crash-report-videos row g-3">

<?php foreach ($items as $fileItem):
    if (!preg_match('/video.*\.ogg$/i', $fileItem->filename)) {
        continue;
    }
    $count++;
    $videoUrl = Url::to(['view-video', 'name' => $fileItem->filename, 'rpt' => $model->id]);
?>
    <div class="col-md-6">
        <div class="card h-100 shadow-sm">
            <video class="card-img-top" controls preload="metadata" style="max-height: 320px; background: #000;">
                <source src="<?= $videoUrl ?>" type="video/ogg">
                Your browser does not support video.
            </video>
            <div class="card-body p-2 text-center small">
                <?= Html::a(Html::encode($fileItem->filename), $videoUrl, ['target' => '_blank', 'rel' => 'noopener']) ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

</div>

<?php if ($count === 0): ?>
    <div class="text-muted fst-italic mt-3">There are no videos available.</div>
<?php endif; ?>
