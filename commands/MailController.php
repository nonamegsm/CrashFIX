<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\MailQueue;

/**
 * Background processor for the application's outbound mail queue.
 *
 * Run from cron / Task Scheduler:
 *
 *   php yii mail/process            # send up to 100 pending mails
 *   php yii mail/process 25         # send up to 25 pending mails
 *   php yii mail/queue --to=foo --subject=Bar --body=Hi
 *
 * Status codes mirror the legacy CrashFix MailStatus lookup:
 *   1 = Pending    2 = Sending    3 = Sent    4 = Failed
 */
class MailController extends Controller
{
    const STATUS_PENDING = 1;
    const STATUS_SENDING = 2;
    const STATUS_SENT    = 3;
    const STATUS_FAILED  = 4;

    /**
     * Send all pending mails. Returns the exit code: 0 on success, 1 if
     * any individual send failed.
     */
    public function actionProcess(int $batch = 100): int
    {
        $rows = MailQueue::find()
            ->where(['status' => [self::STATUS_PENDING, self::STATUS_FAILED]])
            ->orderBy(['create_time' => SORT_ASC])
            ->limit($batch)
            ->all();

        if (empty($rows)) {
            $this->stdout("No pending mails.\n");
            return ExitCode::OK;
        }

        $okCount  = 0;
        $errCount = 0;

        foreach ($rows as $row) {
            // Claim the row so a second worker can't double-send it.
            $row->status = self::STATUS_SENDING;
            $row->save(false, ['status']);

            try {
                $from = (string) (Yii::$app->params['adminEmail'] ?? 'no-reply@crashfix.local');
                Yii::$app->mailer->compose()
                    ->setFrom($from)
                    ->setTo($row->recipient)
                    ->setSubject($row->email_subject)
                    ->setTextBody($row->email_body)
                    ->send();

                $row->status    = self::STATUS_SENT;
                $row->sent_time = time();
                $row->save(false, ['status', 'sent_time']);
                $okCount++;
                $this->stdout("  [OK]   #{$row->id} -> {$row->recipient}\n");
            } catch (\Throwable $e) {
                $row->status = self::STATUS_FAILED;
                $row->save(false, ['status']);
                $errCount++;
                $this->stderr("  [FAIL] #{$row->id} -> {$row->recipient}: " . $e->getMessage() . "\n");
                Yii::error($e->__toString(), 'mail');
            }
        }

        $this->stdout("\nSent: {$okCount}, Failed: {$errCount}\n");
        return $errCount > 0 ? ExitCode::IOERR : ExitCode::OK;
    }

    /**
     * Manually enqueue a mail. Useful for ops smoke tests.
     */
    public function actionQueue(string $to, string $subject = 'CrashFix test', string $body = 'Test'): int
    {
        $row = new MailQueue();
        $row->create_time   = time();
        $row->status        = self::STATUS_PENDING;
        $row->recipient     = $to;
        $row->email_subject = $subject;
        $row->email_headers = "From: no-reply@" . (Yii::$app->params['mailerHostname'] ?? 'localhost');
        $row->email_body    = $body;

        if (!$row->save()) {
            $this->stderr("Could not enqueue: " . print_r($row->errors, true) . "\n");
            return ExitCode::DATAERR;
        }
        $this->stdout("Queued mail #{$row->id} -> {$to}\n");
        return ExitCode::OK;
    }
}
