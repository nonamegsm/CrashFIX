<?php
/** @var yii\web\View $this */
/** @var array $requirements */
use yii\helpers\Html;

$this->title = 'Requirements - CrashFix Setup';
$allPassed = true;
?>

<div class="install-requirements my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 800px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">System Requirements</h4>
        </div>
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Requirement</th>
                        <th class="text-center">Status</th>
                        <th>Memo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $req): ?>
                        <tr class="<?= $req['condition'] ? 'table-success' : 'table-danger' ?>">
                            <td><?= Html::encode($req['name']) ?></td>
                            <td class="text-center">
                                <?php if ($req['condition']): ?>
                                    <span class="badge bg-success">Passed</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Failed</span>
                                    <?php $allPassed = false; ?>
                                <?php endif; ?>
                            </td>
                            <td><small><?= Html::encode($req['memo']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="d-flex justify-content-between mt-4">
                <?= Html::a('Back', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
                <?php if ($allPassed): ?>
                    <?= Html::a('Continue', ['db-config'], ['class' => 'btn btn-primary px-4']) ?>
                <?php else: ?>
                    <button class="btn btn-primary px-4" disabled>Continue</button>
                    <p class="text-danger mt-2 small">Please fix the failures above to continue.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
