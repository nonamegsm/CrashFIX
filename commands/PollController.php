<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Crashreport;
use app\models\Debuginfo;
use app\models\Operation;
use app\models\MailQueue;

/**
 * Periodic site-poll command invoked by the C++ daemon.
 *
 * The CrashFix daemon (crashfixd.exe) repeatedly executes this script
 * via its polling thread; the daemon decides what to do next based on
 * our exit code (0 == healthy). For now this is a thin housekeeping
 * job that:
 *
 *   - cleans up expired Operation rows
 *   - drains the MailQueue (delegates to MailController::actionProcess)
 *
 * The full Yii1 PollCommand (debug-info import, crash-report symbolisation,
 * etc.) will land in a follow-up wave once we wire the daemon's
 * `assync dumper --import-pdb` and `--dump-crash-report` calls into
 * Yii2 storage paths.
 *
 * Invoked from crashfixd.conf as:
 *   SITE_POLL_COMMAND = "C:\xampp\php\php.exe" "C:\xampp\htdocs\greta_crashfix\yii" poll/run
 */
class PollController extends Controller
{
    /**
     * Default action so `php yii poll` works as well as `php yii poll/run`.
     */
    public $defaultAction = 'run';

    public function actionRun(): int
    {
        try {
            $this->cleanupOperations();
            $this->drainMailQueue();
        } catch (\Throwable $e) {
            Yii::error($e->__toString(), 'poll');
            // Returning non-zero here makes the daemon raise its
            // "web app is not configured correctly" alert. Surface it.
            return ExitCode::IOERR;
        }

        return ExitCode::OK;
    }

    /**
     * Drop Operation rows older than 24h that are in a terminal state.
     * Keeps the operation log table from growing without bound.
     */
    protected function cleanupOperations(): void
    {
        $cutoff = time() - 86400;
        Operation::deleteAll([
            'and',
            ['<', 'timestamp', $cutoff],
            ['in', 'status', [
                2, // Succeeded (lookup OperationStatus code 2)
                3, // Failed
            ]],
        ]);
    }

    /**
     * Synchronous drain of the mail queue. Up to 50 mails per poll tick.
     */
    protected function drainMailQueue(): void
    {
        // Re-use the dedicated MailController logic so behaviour stays
        // consistent with manual `yii mail/process` invocation.
        Yii::$app->runAction('mail/process', ['50']);
    }
}
