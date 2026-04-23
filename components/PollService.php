<?php

namespace app\components;

use Yii;
use app\models\Crashreport;
use app\models\Debuginfo;
use app\models\Operation;
use app\models\Processingerror;

/**
 * Full Yii1 PollCommand port for the daemon-driven `php yii poll/run` tick.
 */
class PollService
{
    public const CAC_ERROR = -1;
    public const CAC_STILL_RUNNING = 0;
    public const CAC_COMPLETED = 1;

    public function run(): int
    {
        Yii::info('Entering PollService::run', 'poll');

        if ($this->checkDaemonStatus() !== 0) {
            return 1;
        }

        Operation::deleteOldOperations();

        $this->checkDebugInfoDeletionOperations();
        $this->deletePendingDebugInfoFiles();
        $this->checkDebugInfoProcessingOperations();
        $this->processNewDebugInfoFiles();
        $this->checkCrashReportProcessingOperations();
        $this->processNewCrashReportFiles();

        try {
            Yii::$app->runAction('mail/process', ['batch' => 50]);
        } catch (\Throwable $e) {
            Yii::error('mail/process: ' . $e->getMessage(), 'poll');
        }

        $importDir = Yii::getAlias('@app/import');
        $batchImporter = new BatchImporter();
        $importedCrashReportCount = 0;
        $importedDebugInfoCount = 0;
        $batchImporter->importFiles($importDir, $importedCrashReportCount, $importedDebugInfoCount);

        $this->deleteOldTempFiles();

        Yii::info('Leaving PollService::run', 'poll');
        return 0;
    }

    private function checkDaemonStatus(): int
    {
        Yii::info('Checking daemon status...', 'poll');
        $response = '';
        $retCode = Yii::$app->daemon->getDaemonStatus($response);
        if ($retCode !== 0) {
            Yii::error('Daemon status check returned an error: ' . $retCode . ' ' . $response, 'poll');
            return 1;
        }
        Yii::info('Daemon status check succeeded.', 'poll');
        return 0;
    }

    private function processNewDebugInfoFiles(): void
    {
        Yii::info('Checking for new debug info files ready for import...', 'poll');
        $debugInfoFiles = Debuginfo::find()
            ->where(['status' => Debuginfo::STATUS_WAITING])
            ->limit(100)
            ->all();

        Yii::info($debugInfoFiles === [] ? 'There are no debug info files ready for import'
            : ('Found ' . count($debugInfoFiles) . ' debug info files ready for import'), 'poll');

        $symDir = Yii::$app->storage->getBaseDir() . DIRECTORY_SEPARATOR . 'debugInfo';
        Yii::$app->storage->mkdirRecursive($symDir);

        foreach ($debugInfoFiles as $debugInfo) {
            $fileName = $debugInfo->getLocalFilePath();
            $outFile = tempnam(Yii::$app->getRuntimePath(), 'aop');
            if ($outFile === false) {
                Yii::error('Could not create temp file for debug info import op', 'poll');
                continue;
            }
            $command = 'assync dumper --import-pdb "' . $fileName . '" "' . $symDir . '" "' . $outFile . '"';
            $response = '';
            $retCode = Yii::$app->daemon->execCommand($command, $response);
            if ($retCode !== 0) {
                Yii::error('Error executing command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }
            if (!preg_match(
                '#Assync command\s*\{\s*([0-9]+(?:\.[0-9]+)?)\s*\}\s*has\s*been\s*added\s*to\s*the\s*request\s*queue\.\s*#',
                $response,
                $matches
            )) {
                Yii::error('Unexpected response from command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $op = new Operation();
                $op->status = Operation::STATUS_STARTED;
                $op->timestamp = time();
                $op->srcid = $debugInfo->id;
                $op->cmdid = $matches[1];
                $op->optype = Operation::OPTYPE_IMPORTPDB;
                $op->operand1 = $fileName;
                $op->operand2 = $outFile;
                if (!$op->save()) {
                    throw new \RuntimeException('Could not save an operation record');
                }
                $debugInfo->status = Debuginfo::STATUS_PROCESSING;
                if (!$debugInfo->save(false, ['status'])) {
                    throw new \RuntimeException('Could not save a debug info record');
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Yii::error('An exception caught: ' . $e->getMessage(), 'poll');
            }
        }
        Yii::info('Finished checking for new debug info files ready for import.', 'poll');
    }

    private function checkDebugInfoProcessingOperations(): void
    {
        Yii::info('Checking debug info import operations in progress...', 'poll');
        $operations = Operation::find()
            ->where([
                'optype' => Operation::OPTYPE_IMPORTPDB,
                'status' => Operation::STATUS_STARTED,
            ])
            ->limit(500)
            ->all();

        Yii::info($operations === [] ? 'There are no operations in progress'
            : ('Found ' . count($operations) . ' operations in progress'), 'poll');

        foreach ($operations as $op) {
            $cmdRetCode = -1;
            $cmdRetMsg = '';
            $check = $this->checkAssyncCommand($op->cmdid, $cmdRetCode, $cmdRetMsg);
            if ($check === self::CAC_STILL_RUNNING) {
                continue;
            }

            $opStatus = Operation::STATUS_FAILED;
            if ($check === self::CAC_COMPLETED) {
                if ($cmdRetCode === 0) {
                    if (Debuginfo::importFromDaemonXml((string) $op->operand2, (int) $op->srcid)) {
                        $opStatus = Operation::STATUS_SUCCEEDED;
                    }
                } else {
                    Debuginfo::updateMetadataFromDaemonXmlFile((string) $op->operand2, (int) $op->srcid);
                    Processingerror::record(
                        Processingerror::TYPE_DEBUG_INFO_ERROR,
                        (int) $op->srcid,
                        $cmdRetMsg
                    );
                }
            } elseif ($check === self::CAC_ERROR) {
                Processingerror::record(
                    Processingerror::TYPE_DEBUG_INFO_ERROR,
                    (int) $op->srcid,
                    $cmdRetMsg
                );
            }
            $this->finalizeOperation($op, $opStatus);
        }
        Yii::info('Finished checking debug info import operations in progress.', 'poll');
    }

    private function processNewCrashReportFiles(): void
    {
        Yii::info('Checking for new crash report files ready for processing...', 'poll');
        $crashReportFiles = Crashreport::find()
            ->where(['status' => Crashreport::STATUS_PENDING_PROCESSING])
            ->limit(100)
            ->all();

        Yii::info($crashReportFiles === [] ? 'There are no crash report files ready for processing'
            : ('Found ' . count($crashReportFiles) . ' crash report file(s) ready for processing'), 'poll');

        foreach ($crashReportFiles as $crashReport) {
            $fileName = $crashReport->getLocalFilePath();
            if ($fileName === null || !is_file($fileName)) {
                Yii::error('Missing crash report zip for id ' . $crashReport->id, 'poll');
                continue;
            }
            $outFile = tempnam(Yii::$app->getRuntimePath(), 'aop');
            if ($outFile === false) {
                continue;
            }
            $command = 'assync dumper --dump-crash-report "' . $fileName . '" "' . $outFile . '"';
            $project = $crashReport->project;
            if ($project !== null && !(bool) $project->require_exact_build_age) {
                $command .= ' --relax-build-age';
            }
            $response = '';
            $retCode = Yii::$app->daemon->execCommand($command, $response);
            if ($retCode !== 0) {
                Yii::error('Error executing command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }
            if (!preg_match(
                '#Assync command\s*\{\s*([0-9]+(?:\.[0-9]+)?)\s*\}\s*has\s*been\s*added\s*to\s*the\s*request\s*queue\.\s*#',
                $response,
                $matches
            )) {
                Yii::error('Unexpected response from command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $op = new Operation();
                $op->status = Operation::STATUS_STARTED;
                $op->timestamp = time();
                $op->srcid = $crashReport->id;
                $op->cmdid = $matches[1];
                $op->optype = Operation::OPTYPE_PROCESS_CRASH_REPORT;
                $op->operand1 = $fileName;
                $op->operand2 = $outFile;
                $op->operand3 = $crashReport->srcfilename;
                if (!$op->save()) {
                    throw new \RuntimeException('Could not save an operation record');
                }
                $crashReport->status = Crashreport::STATUS_PROCESSING_IN_PROGRESS;
                if (!$crashReport->save(false, ['status'])) {
                    throw new \RuntimeException('Could not save a crash report record');
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                @unlink($outFile);
                Yii::error('An exception caught: ' . $e->getMessage(), 'poll');
            }
        }
        Yii::info('Finished checking for new crash report files ready for processing.', 'poll');
    }

    private function checkCrashReportProcessingOperations(): void
    {
        Yii::info('Checking crash report processing operations in progress...', 'poll');
        $operations = Operation::find()
            ->where([
                'optype' => Operation::OPTYPE_PROCESS_CRASH_REPORT,
                'status' => Operation::STATUS_STARTED,
            ])
            ->limit(500)
            ->all();

        Yii::info($operations === [] ? 'There are no operations in progress'
            : ('Found ' . count($operations) . ' operations in progress'), 'poll');

        foreach ($operations as $op) {
            $cmdRetCode = -1;
            $cmdRetMsg = '';
            $check = $this->checkAssyncCommand($op->cmdid, $cmdRetCode, $cmdRetMsg);
            if ($check === self::CAC_STILL_RUNNING) {
                continue;
            }

            $opStatus = Operation::STATUS_FAILED;
            $crashReport = Crashreport::findOne((int) $op->srcid);
            if ($crashReport !== null && $crashReport->deleteAssociatedRecords()) {
                if ($check === self::CAC_COMPLETED) {
                    if ($cmdRetCode !== 0) {
                        Processingerror::record(
                            Processingerror::TYPE_CRASH_REPORT_ERROR,
                            (int) $op->srcid,
                            $cmdRetMsg
                        );
                    }
                    if (Crashreport::importFromDaemonXml((string) $op->operand2, (int) $op->srcid)) {
                        $opStatus = Operation::STATUS_SUCCEEDED;
                    }
                } elseif ($check === self::CAC_ERROR) {
                    Processingerror::record(
                        Processingerror::TYPE_CRASH_REPORT_ERROR,
                        (int) $op->srcid,
                        $cmdRetMsg
                    );
                }
            }
            $this->finalizeOperation($op, $opStatus);
        }
        Yii::info('Finished checking crash report processing operations in progress.', 'poll');
    }

    public function checkAssyncCommand(string $cmdId, int &$cmdRetCode, string &$cmdRetMsg): int
    {
        $command = 'daemon get-assync-info -erase-completed ' . $cmdId;
        $response = '';
        Yii::$app->daemon->execCommand($command, $response);
        Yii::info('Command executed: ' . $command . ', Response: "' . $response . '"', 'poll');

        if (preg_match('/still executing/', $response)) {
            Yii::info('The operation ' . $cmdId . ' is still in progress', 'poll');
            return self::CAC_STILL_RUNNING;
        }

        $check = preg_match(
            '#Command\s*\{\s*([0-9]+(?:\.[0-9]+)?)\s*\}\s*returned\s*\{\s*(\d+)\s*([^}]*)\s*\};#',
            $response,
            $matches
        );
        if (!$check || count($matches) !== 4) {
            Yii::error('Unexpected response from command ' . $command . ', response = "' . $response . '"', 'poll');
            $cmdRetMsg = 'CrashFix service has encountered an unexpected internal error during processing this file.';
            return self::CAC_ERROR;
        }
        $cmdRetCode = (int) $matches[2];
        $cmdRetMsg = trim($matches[3]);
        return self::CAC_COMPLETED;
    }

    public function finalizeOperation(Operation $op, int $opStatus): void
    {
        $op->status = $opStatus;
        $op->save(false, ['status']);

        if ((int) $op->optype === Operation::OPTYPE_PROCESS_CRASH_REPORT) {
            $xmlFileName = (string) $op->operand2;
            if ($opStatus !== Operation::STATUS_SUCCEEDED) {
                Yii::error('Setting crash report #' . $op->srcid . ' status to invalid ', 'poll');
                $crashReport = Crashreport::findOne((int) $op->srcid);
                if ($crashReport !== null) {
                    $crashReport->status = Crashreport::STATUS_INVALID;
                    $crashReport->save(false, ['status']);
                }
            }
            @unlink($xmlFileName);
        } elseif ((int) $op->optype === Operation::OPTYPE_IMPORTPDB) {
            $pdbFileName = (string) $op->operand1;
            $xmlFileName = (string) $op->operand2;
            if ($opStatus !== Operation::STATUS_SUCCEEDED) {
                Yii::error('Setting debug info #' . $op->srcid . ' status to invalid ', 'poll');
                $debugInfo = Debuginfo::findOne((int) $op->srcid);
                if ($debugInfo !== null) {
                    $debugInfo->status = Debuginfo::STATUS_INVALID;
                    $debugInfo->save(false, ['status']);
                }
            }
            @unlink($xmlFileName);
            if ($opStatus === Operation::STATUS_SUCCEEDED && is_file($pdbFileName)) {
                @unlink($pdbFileName);
            }
        }
    }

    private function checkDebugInfoDeletionOperations(): void
    {
        Yii::info('Checking debug info deletion operations in progress...', 'poll');
        $operations = Operation::find()
            ->where([
                'optype' => Operation::OPTYPE_DELETE_DEBUG_INFO,
                'status' => Operation::STATUS_STARTED,
            ])
            ->limit(500)
            ->all();

        foreach ($operations as $op) {
            $cmdRetCode = -1;
            $cmdRetMsg = '';
            $check = $this->checkAssyncCommand($op->cmdid, $cmdRetCode, $cmdRetMsg);
            if ($check === self::CAC_STILL_RUNNING) {
                continue;
            }

            $opStatus = Operation::STATUS_FAILED;
            if ($check === self::CAC_COMPLETED) {
                $debugInfo = Debuginfo::findOne((int) $op->srcid);
                if ($debugInfo !== null) {
                    $debugInfo->delete();
                    $opStatus = Operation::STATUS_SUCCEEDED;
                }
            } elseif ($check === self::CAC_ERROR) {
                $debugInfo = Debuginfo::findOne((int) $op->srcid);
                if ($debugInfo !== null) {
                    $debugInfo->delete();
                }
            }
            $this->finalizeOperation($op, $opStatus);
        }
        Yii::info('Finished checking debug info deletion operations in progress.', 'poll');
    }

    private function deletePendingDebugInfoFiles(): void
    {
        Yii::info('Checking for debug info records marked for deletion...', 'poll');
        $debugInfoFiles = Debuginfo::find()
            ->where(['status' => Debuginfo::STATUS_PENDING_DELETE])
            ->limit(20)
            ->all();

        foreach ($debugInfoFiles as $debugInfo) {
            $fileName = $debugInfo->getLocalFilePath();
            $command = 'assync dumper --delete-debug-info "' . $fileName . '"';
            $response = '';
            $retCode = Yii::$app->daemon->execCommand($command, $response);
            if ($retCode !== 0) {
                Yii::error('Error executing command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }
            if (!preg_match(
                '#Assync command \{([0-9]{1,6}\.[0-9]{1,9})\} has been added to the request queue\.#',
                $response,
                $matches
            )) {
                Yii::error('Unexpected response from command ' . $command . ', response = ' . $response, 'poll');
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $op = new Operation();
                $op->status = Operation::STATUS_STARTED;
                $op->timestamp = time();
                $op->srcid = $debugInfo->id;
                $op->cmdid = $matches[1];
                $op->optype = Operation::OPTYPE_DELETE_DEBUG_INFO;
                $op->operand1 = $fileName;
                if (!$op->save()) {
                    throw new \RuntimeException('Could not save an operation record');
                }
                $debugInfo->status = Debuginfo::STATUS_DELETE_IN_PROGRESS;
                if (!$debugInfo->save(false, ['status'])) {
                    throw new \RuntimeException('Could not save a debug info record');
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                Yii::error('An exception caught: ' . $e->getMessage(), 'poll');
            }
        }
        Yii::info('Finished checking for debug info files ready for deletion.', 'poll');
    }

    private function deleteOldTempFiles(): void
    {
        $dirName = Yii::$app->getRuntimePath();
        if (!is_dir($dirName)) {
            return;
        }
        $fileList = scandir($dirName);
        if ($fileList === false) {
            return;
        }
        foreach ($fileList as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fileName = $dirName . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fileName)) {
                continue;
            }
            $path_parts = pathinfo($fileName);
            if (isset($path_parts['extension']) && strtolower((string) $path_parts['extension']) === 'log') {
                continue;
            }
            $modificationTime = filemtime($fileName);
            if ($modificationTime !== false && $modificationTime < (time() - 86400)) {
                @unlink($fileName);
            }
        }
    }
}
