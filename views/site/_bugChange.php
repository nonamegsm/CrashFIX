<?php
/** @var yii\web\View $this */
/** @var app\models\BugChange $model */

use yii\helpers\Html;
use app\components\MiscHelpers;
use app\models\Lookup;

$user      = $model->user;
$bug       = $model->bug;
$status    = $model->statuschange;
$comment   = $model->comment;
$attaches  = $model->attachments;
$initial   = $model->isInitialChange();
?>

<div class="bug-change card mb-3">
    <div class="card-body p-3">
        <div class="bug-change-header small text-muted mb-2">
            <?= Html::a('Bug #' . $model->bug_id, ['bug/view', 'id' => $model->bug_id]) ?>
            <?= $initial ? 'has been opened' : 'has been changed' ?>
            by <strong><?= $user ? Html::encode($user->username) : 'unknown' ?></strong>
            on <?= date('j F Y, G:i', (int) $model->timestamp) ?>
        </div>

        <?php if ($initial && $bug !== null): ?>
            <div class="bug-change-comment fst-italic mb-2">
                <?= Html::encode(MiscHelpers::addEllipsis($bug->summary, 150)) ?>
            </div>
        <?php endif; ?>

        <?php if ($comment !== null && !empty($comment->text)): ?>
            <div class="bug-change-comment p-2 bg-light border-start border-3 mb-2">
                <?= nl2br(Html::encode(MiscHelpers::addEllipsis($comment->text, 150))) ?>
            </div>
        <?php endif; ?>

        <?php if ($status !== null): ?>
            <ul class="bug-change-status list-unstyled small mb-2">
                <?php if ($status->status !== null): ?>
                    <li><strong>Status:</strong> <?= Html::encode(Lookup::item('BugStatus', (int) $status->status) ?: $status->status) ?></li>
                <?php endif; ?>
                <?php if ($status->merged_into !== null): ?>
                    <li><strong>Merged into bug:</strong> <?= Html::a('#' . (int) $status->merged_into, ['bug/view', 'id' => (int) $status->merged_into]) ?></li>
                <?php endif; ?>
                <?php if ($status->priority !== null): ?>
                    <li><strong>Priority:</strong> <?= Html::encode(Lookup::item('BugPriority', (int) $status->priority) ?: $status->priority) ?></li>
                <?php endif; ?>
                <?php if ($status->reproducability !== null): ?>
                    <li><strong>Reproducibility:</strong> <?= Html::encode(Lookup::item('BugReproducability', (int) $status->reproducability) ?: $status->reproducability) ?></li>
                <?php endif; ?>
                <?php if ($status->assigned_to !== null && ($owner = $status->getOwner()) !== null): ?>
                    <li><strong>Owner:</strong> <?= Html::encode($owner->username) ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <?php foreach ($attaches as $attachment): ?>
            <div class="bug-change-attach small">
                <i class="fa fa-paperclip text-muted me-1" aria-hidden="true"></i>
                File attached:
                <?= Html::a(Html::encode($attachment->filename), ['bug/download-attachment', 'id' => $attachment->id]) ?>
                <span class="text-muted">(<?= MiscHelpers::fileSizeToStr((int) $attachment->filesize) ?>)</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
