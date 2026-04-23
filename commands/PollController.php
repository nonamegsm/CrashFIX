<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\components\PollService;

/**
 * Periodic site-poll command invoked by the C++ daemon.
 *
 * The CrashFix daemon (crashfixd.exe) repeatedly executes this script
 * via its polling thread; the daemon treats exit code 0 as healthy.
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
            $code = (new PollService())->run();
        } catch (\Throwable $e) {
            Yii::error($e->__toString(), 'poll');
            return ExitCode::IOERR;
        }

        return $code === 0 ? ExitCode::OK : ExitCode::IOERR;
    }
}
