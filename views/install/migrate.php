<?php
/** @var yii\web\View $this */
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Database Migrations - CrashFix Setup';

$runUrl = Url::to(['install/run-migrations']);
$redirectUrl = Url::to(['install/create-admin']);
?>

<div class="install-migrate my-5">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-white">
            <h4 class="mb-0">Setup Database Schema</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted mb-4">Click <strong>Initialize Database</strong> to create all required tables and seed initial data. This may take a few seconds.</p>

            <div id="migration-status" class="alert alert-info py-3 mb-4 d-none">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Running database setup, please wait...
            </div>

            <div id="migration-error" class="alert alert-danger d-none"></div>

            <div class="d-flex justify-content-between mt-3">
                <?= Html::a('Back', ['db-config'], ['class' => 'btn btn-outline-secondary', 'id' => 'btn-back']) ?>
                <button id="btn-run-migrate" class="btn btn-primary px-4">Initialize Database</button>
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
            data: { _csrf: yii.getCsrfToken() },
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
?>
