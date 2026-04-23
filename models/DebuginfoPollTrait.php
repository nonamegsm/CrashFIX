<?php

namespace app\models;

use Yii;

/**
 * Poll / daemon XML import for debug-info rows (Yii1 PollCommand helpers).
 */
trait DebuginfoPollTrait
{
    /** Pending daemon deletion (codes 5/6 are DWARF states in Yii2). */
    public const STATUS_PENDING_DELETE     = 7;
    public const STATUS_DELETE_IN_PROGRESS = 8;

    public function getLocalFilePath(): string
    {
        if (!Yii::$app->has('storage')) {
            return '';
        }
        return Yii::$app->storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename);
    }

    public static function importFromDaemonXml(string $xmlFileName, int $debugInfoId): bool
    {
        $debugInfo = static::findOne($debugInfoId);
        if ($debugInfo === null) {
            Yii::warning('Not found debug info id=' . $debugInfoId, 'poll');
            return false;
        }

        $status = false;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $doc = @simplexml_load_file($xmlFileName);
            if ($doc === false || $doc === null) {
                throw new \RuntimeException('Invalid XML document ' . $xmlFileName);
            }
            $elemSummary = $doc->Summary;
            if ($elemSummary === null) {
                throw new \RuntimeException('Not found Summary element in XML document ' . $xmlFileName);
            }
            $guid = $elemSummary->GUID;
            $age = $elemSummary->Age;
            if ($guid === null || $age === null) {
                throw new \RuntimeException('Not found Summary->GUID or Summary->Age element in XML document ' . $xmlFileName);
            }
            $debugInfo->guid = (string) $guid . (string) $age;
            $debugInfo->status = self::STATUS_READY;
            static::applyDetectorMetadataFromSummary($elemSummary, $debugInfo);
            if (!$debugInfo->save()) {
                throw new \RuntimeException('Error saving debug info AR to database.');
            }
            $transaction->commit();
            $status = true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage(), 'poll');
            Processingerror::record(
                Processingerror::TYPE_DEBUG_INFO_ERROR,
                $debugInfoId,
                $e->getMessage()
            );
            $status = false;
        }

        return $status;
    }

    public static function updateMetadataFromDaemonXmlFile(?string $xmlFileName, int $debugInfoId): void
    {
        if ($xmlFileName === null || $xmlFileName === '' || !is_file($xmlFileName)) {
            return;
        }
        $debugInfo = static::findOne($debugInfoId);
        if ($debugInfo === null) {
            return;
        }
        $doc = @simplexml_load_file($xmlFileName);
        if ($doc === false || $doc === null || $doc->Summary === null) {
            return;
        }
        static::applyDetectorMetadataFromSummary($doc->Summary, $debugInfo);
        try {
            $debugInfo->save(false, ['format', 'container', 'architecture', 'build_id_kind']);
        } catch (\Throwable $e) {
            Yii::warning('updateMetadataFromDaemonXmlFile: save failed: ' . $e->getMessage(), 'poll');
        }
    }

    private static function applyDetectorMetadataFromSummary(\SimpleXMLElement $elemSummary, Debuginfo $debugInfo): void
    {
        $fmt  = isset($elemSummary->Format) ? (string) $elemSummary->Format : '';
        $cont = isset($elemSummary->Container) ? (string) $elemSummary->Container : '';
        $arch = isset($elemSummary->Architecture) ? (string) $elemSummary->Architecture : '';
        $kind = isset($elemSummary->BuildIdKind) ? (string) $elemSummary->BuildIdKind : '';

        if ($fmt === '') {
            $fmt = self::FORMAT_PDB;
        }
        if ($cont === '') {
            $cont = 'pdb';
        }
        if ($kind === '') {
            $kind = self::BUILDID_PDB_GUID_AGE;
        }

        $debugInfo->format        = $fmt !== '' ? $fmt : null;
        $debugInfo->container     = $cont !== '' ? $cont : null;
        $debugInfo->architecture  = $arch !== '' ? $arch : null;
        $debugInfo->build_id_kind = $kind !== '' ? $kind : null;
    }
}
