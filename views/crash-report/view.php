<?php
/** @var yii\web\View $this */
/** @var app\models\Crashreport $model */
/** @var string $activeItem */
/** @var int|null $thread */
/** @var app\models\Thread|null $threadModel */
/** @var yii\data\ActiveDataProvider|null $stackTrace */
/** @var yii\data\ActiveDataProvider|null $customProps */
/** @var yii\data\ActiveDataProvider|null $screenshots */
/** @var yii\data\ActiveDataProvider|null $videos */
/** @var yii\data\ActiveDataProvider|null $modules */
/** @var yii\data\ActiveDataProvider|null $threads */

use yii\bootstrap4\Nav;
use yii\helpers\Html;

$this->title = Yii::$app->name . ' - View Report #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Crash Reports', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'View Report #' . $model->id;

/**
 * Build a "Tab Name (count)" label, dimming the link when there is nothing
 * to show (matches the legacy menu-item-grayed treatment).
 */
$tab = function (string $name, ?\yii\data\ActiveDataProvider $dp) use ($model, $activeItem): array {
    $count = $dp ? (int) $dp->getTotalCount() : 0;
    $label = $count > 0 ? $name . ' (' . $count . ')' : $name;
    $opts  = [];
    if ($count === 0 && $name !== 'Summary' && $name !== 'Files') {
        $opts['class'] = 'text-muted';
    }
    return [
        'label'       => $label,
        'url'         => ['view', 'id' => $model->id, 'tab' => str_replace(' ', '', $name)],
        'active'      => ($activeItem === str_replace(' ', '', $name)),
        'linkOptions' => $opts,
    ];
};
?>

<div class="crash-report-view">

    <?= Nav::widget([
        'options' => ['class' => 'nav-tabs mb-3'],
        'items'   => [
            $tab('Summary',     null),
            $tab('Files',       null),
            $tab('CustomProps', $customProps),
            $tab('Screenshots', $screenshots),
            $tab('Videos',      $videos),
            $tab('Threads',     $threads),
            $tab('Modules',     $modules),
        ],
    ]) ?>

    <div class="tab-content">
    <?php
    $params = [
        'model'       => $model,
        'stackTrace'  => $stackTrace ?? null,
        'threadModel' => $threadModel ?? null,
    ];

    switch ($activeItem) {
        case 'Summary':     echo $this->render('_viewSummary',     $params); break;
        case 'Files':       echo $this->render('_viewFiles',       $params); break;
        case 'CustomProps': echo $this->render('_viewCustomProps', $params); break;
        case 'Screenshots': echo $this->render('_viewScreenshots', $params); break;
        case 'Videos':      echo $this->render('_viewVideos',      $params); break;
        case 'Threads':     echo $this->render('_viewThreads',     $params); break;
        case 'Modules':     echo $this->render('_viewModules',     $params); break;
        default:
            echo Html::tag('div', 'Unknown tab: ' . Html::encode($activeItem), [
                'class' => 'alert alert-warning',
            ]);
    }
    ?>
    </div>
</div>
