<?php
/** @var yii\web\View $this */
/** @var bool $existingYii1 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Database Migrations - CrashFix Setup';

$runUrl = Url::to(['install/run-migrations']);
$redirectUrl = Url::to(['install/create-admin']);
$legacyAdopt = $existingYii1 ? '1' : '0';
?>

<div class="install-migrate my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 640px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">Database schema</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($existingYii1): ?>
                <div class="alert alert-warning small">
                    <strong>Existing Yii1 database.</strong>
                    Migrations run in <em>adopt</em> mode: if a table, column, or seed row
                    already exists (typical when upgrading in place), that step is recorded as
                    done instead of failing. Take a backup first if you are unsure.
                </div>
            <?php else: ?>
                <p class="text-muted mb-4">
                    Click <strong>Run migrations</strong> to create tables and seed lookup data.
                    This may take a few seconds.
                </p>
            <?php endif; ?>

            <div id="migration-status" class="alert alert-info py-3 mb-4 d-none">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Running database setup, please wait...
            </div>

            <div id="migration-error" class="alert alert-danger d-none"></div>

            <div class="d-flex justify-content-between mt-3">
                <?= Html::a('Back', ['db-config'], ['class' => 'btn btn-outline-secondary', 'id' => 'btn-back']) ?>
                <button type="button" id="btn-run-migrate" class="btn btn-primary px-4">Run migrations</button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
    $("#btn-run-migrate").on('click', function() {
        $(this).prop('disabled', true).text('Working...');
        $("#btn-back").prop('disabled', true);
        $("#migration-status").removeClass('d-none');
        $("#migration-error").addClass('d-none');

        $.ajax({
            url: "$runUrl",
            type: "POST",
            data: { _csrf: yii.getCsrfToken(), legacy_adopt: "$legacyAdopt" },
            dataType: "json",
            success: function(resp) {
                if (resp.success) {
                    window.location.href = "$redirectUrl";
                } else {
                    $("#migration-status").addClass('d-none');
                    $("#migration-error").removeClass('d-none').text("Error: " + resp.message);
                    $("#btn-run-migrate").prop('disabled', false).text('Retry');
                    $("#btn-back").prop('disabled', false);
                }
            },
            error: function(xhr) {
                $("#migration-status").addClass('d-none');
                $("#migration-error").removeClass('d-none').text("Unexpected error: " + xhr.responseText);
                $("#btn-run-migrate").prop('disabled', false).text('Retry');
                $("#btn-back").prop('disabled', false);
            }
        });
    });
JS
);
