<?php

namespace app\models;

use Yii;
use app\components\MiscHelpers;

/**
 * Daemon poll / XML import helpers ported from the Yii1 CrashReport model
 * and PollCommand::importCrashReportFromXml.
 */
trait CrashreportPollTrait
{
    public const STATUS_PENDING_PROCESSING     = 1;
    public const STATUS_PROCESSING_IN_PROGRESS = 2;
    public const STATUS_PROCESSED              = 3;
    public const STATUS_INVALID                = 4;

    public function getLocalFilePath(): ?string
    {
        if (!isset($this->md5) || strlen((string) $this->md5) !== 32) {
            return null;
        }
        if (!Yii::$app->has('storage')) {
            return null;
        }
        return Yii::$app->storage->crashReportPath((int) $this->project_id, (int) $this->id);
    }

    public function deleteAssociatedRecords(): bool
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            foreach (Thread::find()->where(['crashreport_id' => $this->id])->all() as $thread) {
                Stackframe::deleteAll(['thread_id' => $thread->id]);
                $thread->delete();
            }
            Module::deleteAll(['crashreport_id' => $this->id]);
            Fileitem::deleteAll(['crashreport_id' => $this->id]);
            Customprop::deleteAll(['crashreport_id' => $this->id]);
            Processingerror::deleteAll([
                'type' => Processingerror::TYPE_CRASH_REPORT_ERROR,
                'srcid' => $this->id,
            ]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), 'poll');
            return false;
        }
        return true;
    }

    public function checkQuota(): bool
    {
        $project = Project::findOne((int) $this->project_id);
        if ($project === null) {
            $this->addError('project_id', 'Invalid project ID.');
            return false;
        }
        if ((int) $project->crash_report_files_disc_quota > 0) {
            $totalFileSize = 0;
            $percentOfQuota = 0;
            $project->getCrashReportCount($totalFileSize, $percentOfQuota);
            if ($project->crash_report_files_disc_quota * 1024 * 1024 < $totalFileSize + (int) $this->filesize) {
                $this->addError('fileAttachment', 'Crash report disc quota for this project has exceeded.');
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $md5Hash output stack-trace hash or title md5
     */
    public function getCrashGroupTitle(string &$md5Hash): string
    {
        $title = '';
        $hash = '';

        $proj = Project::findOne((int) $this->project_id);
        if ($proj === null) {
            $title = 'Unknown Project';
        } elseif ((int) $this->status === self::STATUS_INVALID) {
            $title = 'Invalid Reports';
        } elseif ((int) $this->status !== self::STATUS_PROCESSED) {
            $title = 'Unsorted Reports';
        } else {
            foreach ($this->customProps as $prop) {
                if ($prop->name === 'CollectionId') {
                    $title = (string) $prop->value;
                }
            }

            if ($title === '' && (int) $this->exception_thread_id === 0) {
                $title = 'Reports without Exception Info';
            }

            if ($title === '') {
                $exceptionThread = null;
                foreach ($this->threads as $thread) {
                    if ((int) $thread->thread_id === (int) $this->exception_thread_id) {
                        $exceptionThread = $thread;
                        break;
                    }
                }
                if ($exceptionThread !== null && $exceptionThread->stack_trace_md5 !== null && $exceptionThread->stack_trace_md5 !== '') {
                    $frameTitle = $exceptionThread->getExceptionStackFrameTitle();
                    if ($frameTitle !== '') {
                        $title = $frameTitle;
                        $hash = (string) $exceptionThread->stack_trace_md5;
                    }
                }
            }

            if ($title === '') {
                if ($this->exceptionmodule !== null && $this->exceptionmodule !== ''
                    && $this->exceptionmodulebase !== null && (string) $this->exceptionmodulebase !== '') {
                    $shortModuleName = (string) $this->exceptionmodule;
                    $pos = strrchr($shortModuleName, '\\');
                    if ($pos !== false) {
                        $shortModuleName = substr($pos, 1);
                    }
                    $offsetInModule = (int) $this->exceptionaddress - (int) $this->exceptionmodulebase;
                    $title = sprintf('%s!+0x%x', $shortModuleName, $offsetInModule);
                } else {
                    $title = 'Reports without Exception Info';
                }
            }
        }

        $title = MiscHelpers::addEllipsis($title, 200);
        if ($hash === '') {
            $hash = md5($title);
        }
        $md5Hash = $hash;
        return $title;
    }

    public function createCrashGroup(): ?Crashgroup
    {
        $crashGroupMd5 = '';
        $crashGroupTitle = $this->getCrashGroupTitle($crashGroupMd5);

        Yii::info('Crash group title = ' . $crashGroupTitle, 'poll');
        Yii::info('Crash group MD5 = ' . $crashGroupMd5, 'poll');

        $crashGroup = Crashgroup::find()
            ->where([
                'project_id' => $this->project_id,
                'appversion_id' => $this->appversion_id,
                'md5' => $crashGroupMd5,
            ])->one();

        if ($crashGroup === null) {
            $count = Crashgroup::find()
                ->where(['project_id' => $this->project_id, 'appversion_id' => $this->appversion_id])
                ->andWhere(['like', 'title', $crashGroupTitle])
                ->count();
            if ((int) $count !== 0) {
                $crashGroupTitle .= ' (' . ((int) $count + 1) . ')';
            }

            $crashGroup = new Crashgroup();
            $crashGroup->title = $crashGroupTitle;
            $crashGroup->md5 = $crashGroupMd5;
            $crashGroup->project_id = $this->project_id;
            $crashGroup->appversion_id = $this->appversion_id;
            $crashGroup->created = time();
            $crashGroup->status = Crashgroup::STATUS_NEW;
            if (!$crashGroup->save()) {
                return null;
            }
        }

        return $crashGroup;
    }

    /**
     * Parses daemon output XML into tbl_crashreport + related rows.
     * Port of PollCommand::importCrashReportFromXml.
     */
    public static function importFromDaemonXml(string $xmlFileName, int $crashReportId): bool
    {
        $status = false;
        $crashReport = self::findOne($crashReportId);
        if ($crashReport === null) {
            Yii::warning('Not found crash report id=' . $crashReportId, 'poll');
            return false;
        }

        $crashReport->status = self::STATUS_PROCESSED;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $doc = @simplexml_load_file($xmlFileName);
            if ($doc === false || $doc === null) {
                throw new \RuntimeException('CrashFix service has encountered an error when retrieving information from crash report file');
            }
            $elemSummary = $doc->Summary;
            if ($elemSummary === null) {
                throw new \RuntimeException('Internal error: not found Summary element in XML document ' . $xmlFileName);
            }

            $generatorVersion = (int) $elemSummary->GeneratorVersion;
            $crashGuid = (string) $elemSummary->CrashGUID;
            $appVersion = (string) $elemSummary->ApplicationVersion;
            $exeImage = (string) $elemSummary->ExecutableImage;
            $dateCreated = (string) $elemSummary->DateCreatedUTC;
            $osNameReg = (string) $elemSummary->OSNameReg;
            $osVersionMinidump = (string) $elemSummary->OSVersionMinidump;
            $osIs64Bit = (int) $elemSummary->OSIs64Bit;
            $geoLocation = (string) $elemSummary->GeographicLocation;
            $productType = (string) $elemSummary->ProductType;
            $cpuArchitecture = (string) $elemSummary->CPUArchitecture;
            $cpuCount = (int) $elemSummary->CPUCount;
            $guiResourceCount = (int) $elemSummary->GUIResourceCount;
            $openHandleCount = $elemSummary->OpenHandleCount;
            $memoryUsageKbytes = $elemSummary->MemoryUsageKbytes;
            $exceptionType = (string) $elemSummary->ExceptionType;
            $exceptionAddress = $elemSummary->ExceptionAddress;
            $sehExceptionCode = $elemSummary->SEHExceptionCode;
            $exceptionThreadID = $elemSummary->ExceptionThreadID;
            $exceptionModuleName = (string) $elemSummary->ExceptionModuleName;

            $exceptionModuleBaseRaw = (string) $elemSummary->ExceptionModuleBase;

            $userEmail = (string) $elemSummary->UserEmail;
            $problemDescription = (string) $elemSummary->ProblemDescription;

            $crashReport->status = self::STATUS_PROCESSED;
            $crashReport->crashrptver = (string) $generatorVersion;
            $crashReport->crashguid = $crashGuid;

            $ver = Appversion::createIfNotExists($appVersion, (int) $crashReport->project_id);
            $crashReport->appversion_id = $ver->id;

            if ($userEmail !== '') {
                $crashReport->emailfrom = $userEmail;
            }
            if ($problemDescription !== '') {
                $crashReport->description = $problemDescription;
            }
            if ($dateCreated !== '') {
                $crashReport->date_created = strtotime($dateCreated) ?: time();
            }

            $crashReport->os_name_reg = $osNameReg;
            $crashReport->os_ver_mdmp = $osVersionMinidump;
            $crashReport->os_is_64bit = $osIs64Bit;
            $crashReport->geo_location = $geoLocation;
            $crashReport->product_type = $productType;
            $crashReport->cpu_architecture = $cpuArchitecture;
            $crashReport->cpu_count = $cpuCount;
            $crashReport->gui_resource_count = $guiResourceCount;
            $crashReport->memory_usage_kbytes = $memoryUsageKbytes !== null ? (int) $memoryUsageKbytes : null;
            $crashReport->open_handle_count = $openHandleCount !== null ? (int) $openHandleCount : null;
            $crashReport->exception_type = $exceptionType;

            if ($sehExceptionCode !== null && (string) $sehExceptionCode !== '') {
                $crashReport->exception_code = (int) $sehExceptionCode;
            }
            if ($exceptionThreadID !== null && (string) $exceptionThreadID !== '') {
                $crashReport->exception_thread_id = (int) $exceptionThreadID;
            }
            if ($exceptionAddress !== null && (string) $exceptionAddress !== '') {
                $crashReport->exceptionaddress = (int) $exceptionAddress;
            }
            if ($exceptionModuleName !== '') {
                $crashReport->exceptionmodule = $exceptionModuleName;
            }
            if ($exceptionModuleBaseRaw !== '') {
                $crashReport->exceptionmodulebase = (int) $exceptionModuleBaseRaw;
            }
            $crashReport->exe_image = $exeImage;

            if (!$crashReport->validate()) {
                foreach ($crashReport->getErrors() as $fieldName => $fieldErrors) {
                    foreach ($fieldErrors as $errorMsg) {
                        $cur = $crashReport->getAttribute($fieldName);
                        Yii::error('Error in crashreport data (' . $cur . '): ' . $errorMsg, 'poll');
                        Processingerror::record(
                            Processingerror::TYPE_CRASH_REPORT_ERROR,
                            (int) $crashReport->id,
                            $errorMsg . ' (' . $cur . ')'
                        );
                        $crashReport->$fieldName = null;
                    }
                }
                $crashReport->clearErrors();
            }

            $elemFileList = $doc->FileList;
            if ($elemFileList !== null) {
                $i = 0;
                foreach ($elemFileList->Row as $elemRow) {
                    $i++;
                    if ($i === 1) {
                        continue;
                    }
                    $itemName = (string) $elemRow->Cell[1]['val'];
                    $itemDesc = (string) $elemRow->Cell[2]['val'];
                    $fileItem = new Fileitem();
                    $fileItem->filename = $itemName;
                    $fileItem->description = $itemDesc;
                    $fileItem->crashreport_id = $crashReportId;
                    if (!$fileItem->save()) {
                        throw new \RuntimeException('Could not save file item record');
                    }
                }
            }

            $elemAppDefinedProps = $doc->ApplicationDefinedProperties;
            if ($elemAppDefinedProps !== null) {
                $i = 0;
                foreach ($elemAppDefinedProps->Row as $elemRow) {
                    $i++;
                    if ($i === 1) {
                        continue;
                    }
                    $name = (string) $elemRow->Cell[1]['val'];
                    $val = (string) $elemRow->Cell[2]['val'];
                    $customProp = new Customprop();
                    $customProp->name = $name;
                    $customProp->value = $val;
                    $customProp->crashreport_id = $crashReportId;
                    $saved = false;
                    try {
                        $saved = $customProp->save();
                    } catch (\Throwable $e) {
                        $saved = false;
                    }
                    if (!$saved) {
                        $customProp = new Customprop();
                        $customProp->name = $name;
                        $conv = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $val);
                        $customProp->value = $conv !== false ? (string) @iconv('ISO-8859-1', 'UTF-8', $conv) : $val;
                        $customProp->crashreport_id = $crashReportId;
                        try {
                            $saved = $customProp->save();
                        } catch (\Throwable $e) {
                            $saved = false;
                        }
                    }
                    if (!$saved) {
                        $customProp = new Customprop();
                        $customProp->name = $name;
                        $customProp->value = 'INVALID';
                        $customProp->crashreport_id = $crashReportId;
                        if (!$customProp->save()) {
                            throw new \RuntimeException('Could not save custom property record');
                        }
                    }
                }
            }

            $elemModuleList = $doc->ModuleList;
            if ($elemModuleList !== null && count($elemModuleList->Row) > 0) {
                $i = 0;
                foreach ($elemModuleList->Row as $elemRow) {
                    $i++;
                    if ($i === 1) {
                        continue;
                    }
                    $name = (string) $elemRow->Cell[1]['val'];
                    $symLoadStatus = (int) $elemRow->Cell[2]['val'];
                    $loadedPdbGUID = (string) $elemRow->Cell[4]['val'];
                    $fileVersion = (string) $elemRow->Cell[5]['val'];
                    $timeStamp = $elemRow->Cell[6]['val'] !== null ? (int) $elemRow->Cell[6]['val'] : null;
                    $guidnAge = (string) $elemRow->Cell[7]['val'];

                    $module = new Module();
                    $module->crashreport_id = $crashReportId;
                    $module->name = $name;
                    $module->sym_load_status = $symLoadStatus;
                    $module->file_version = $fileVersion;
                    $module->timestamp = $timeStamp;
                    $module->matching_pdb_guid = $guidnAge;

                    $debugInfo = Debuginfo::findOne(['guid' => $loadedPdbGUID]);
                    if ($debugInfo !== null) {
                        $module->loaded_debug_info_id = $debugInfo->id;
                    }
                    if (!$module->save()) {
                        throw new \RuntimeException('Could not save module record');
                    }
                }
            }

            foreach ($doc->StackTrace as $elemStackTrace) {
                $threadId = (int) $elemStackTrace->ThreadID;
                $stackTraceMD5 = (string) $elemStackTrace->StackTraceMD5;

                $thread = new Thread();
                $thread->thread_id = $threadId;
                $thread->crashreport_id = $crashReportId;
                if ($stackTraceMD5 !== '') {
                    $thread->stack_trace_md5 = $stackTraceMD5;
                }
                if (!$thread->save()) {
                    throw new \RuntimeException('Could not save thread record');
                }

                $i = 0;
                foreach ($elemStackTrace->Row as $elemRow) {
                    $i++;
                    if ($i === 1) {
                        continue;
                    }
                    $addrPC = (int) $elemRow->Cell[1]['val'];
                    $moduleName = (string) $elemRow->Cell[2]['val'];
                    $offsInModule = $elemRow->Cell[3]['val'] !== null && (string) $elemRow->Cell[3]['val'] !== ''
                        ? (int) $elemRow->Cell[3]['val'] : null;
                    $symName = (string) $elemRow->Cell[4]['val'];
                    $undSymName = (string) $elemRow->Cell[5]['val'];
                    $offsInSym = $elemRow->Cell[6]['val'] !== null && (string) $elemRow->Cell[6]['val'] !== ''
                        ? (int) $elemRow->Cell[6]['val'] : null;
                    $srcFile = (string) $elemRow->Cell[7]['val'];
                    $srcLine = $elemRow->Cell[8]['val'] !== null && (string) $elemRow->Cell[8]['val'] !== ''
                        ? (int) $elemRow->Cell[8]['val'] : null;
                    $offsInLine = $elemRow->Cell[9]['val'] !== null && (string) $elemRow->Cell[9]['val'] !== ''
                        ? (int) $elemRow->Cell[9]['val'] : null;

                    $stackFrame = new Stackframe();
                    $stackFrame->thread_id = $thread->id;
                    $stackFrame->addr_pc = $addrPC;

                    if ($moduleName !== '') {
                        $module = Module::findOne(['name' => $moduleName, 'crashreport_id' => $crashReportId]);
                        if ($module !== null) {
                            $stackFrame->module_id = $module->id;
                        }
                        $stackFrame->offs_in_module = $offsInModule;
                    }
                    if ($symName !== '') {
                        $stackFrame->symbol_name = $symName;
                    }
                    if ($undSymName !== '') {
                        $stackFrame->und_symbol_name = $undSymName;
                    }
                    if ($offsInSym !== null) {
                        $stackFrame->offs_in_symbol = $offsInSym;
                    }
                    if ($srcFile !== '') {
                        $stackFrame->src_file_name = $srcFile;
                    }
                    if ($srcLine !== null) {
                        $stackFrame->src_line = $srcLine;
                    }
                    if ($offsInLine !== null) {
                        $stackFrame->offs_in_line = $offsInLine;
                    }
                    if (!$stackFrame->save()) {
                        throw new \RuntimeException('Could not save stack frame record');
                    }
                }
            }

            $transaction->commit();
            $status = true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), 'poll');
            $crashReport->status = self::STATUS_INVALID;
            Processingerror::record(
                Processingerror::TYPE_CRASH_REPORT_ERROR,
                (int) $crashReport->id,
                $e->getMessage()
            );
            $status = false;
        }

        $crashReport = self::findOne($crashReportId);
        if ($crashReport === null) {
            return false;
        }

        $crashGroup = $crashReport->createCrashGroup();
        if ($crashGroup === null) {
            Yii::error('Error creating crash group', 'poll');
            $status = false;
        } else {
            $crashReport->groupid = $crashGroup->id;
            $saved = $crashReport->save();
            if (!$saved) {
                Yii::error('Error saving AR crashReport', 'poll');
                $status = false;
            }
            if (!$saved || !$crashReport->checkQuota()) {
                Yii::error('Error checking crash report quota', 'poll');
                $status = false;
                $again = self::findOne($crashReportId);
                if ($again !== null) {
                    $again->delete();
                }
            }
        }

        return $status;
    }
}
