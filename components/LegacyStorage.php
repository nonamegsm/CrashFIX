<?php

namespace app\components;

use Yii;

/**
 * Drop-in replacement for {@see Storage} that resolves file paths the
 * way the legacy Yii1 CrashFix server did, so a fresh Yii2 install can
 * be pointed at an existing legacy webroot for read-only or mixed
 * deployment alongside the original app.
 *
 * Path differences between the two layouts:
 *
 *   Yii2 (default Storage)
 *     <basePath>/projects/{project_id}/crashreports/{report_id}.zip
 *     <basePath>/projects/{project_id}/debuginfo/{id}_{filename}
 *     <basePath>/projects/{project_id}/bug_attachments/{id}_{filename}
 *
 *   Yii1 (this class - LegacyStorage)
 *     <basePath>/crashReports/{md5[0:3]}/{md5[3:6]}/{md5}.zip
 *     <basePath>/debugInfo/{filename}/{guid}/{filename}
 *     <basePath>/bugAttachments/{md5[0:3]}/{md5[3:6]}/{md5}
 *
 * To use:
 *   1. Drop this file under app/components/.
 *   2. In config/web.php point `storage` at it and at the legacy data dir:
 *        'storage' => [
 *            'class'    => 'app\components\LegacyStorage',
 *            'basePath' => '/var/www/crashfix-old/protected/data',
 *        ],
 *
 * Everything else in the Yii2 app (CrashreportController, DebuginfoController,
 * BugController) is unchanged - it only ever calls Storage's public API.
 */
class LegacyStorage extends Storage
{
    /**
     * @param int $projectId  unused on the legacy layout (sharded by md5)
     * @param int $reportId   unused; we look the report up to get its md5
     */
    public function crashReportPath(int $projectId, int $reportId): string
    {
        $md5 = $this->lookupCrashReportMd5($reportId);
        return $this->md5ShardedPath('crashReports', $md5, '.zip');
    }

    public function crashReportExtractDir(int $projectId, int $reportId): string
    {
        // The legacy layout kept the original archive only; on-demand
        // extracts went into runtime/. We point at the new app's runtime
        // area so we never write into the legacy webroot.
        return rtrim(Yii::getAlias('@runtime/legacy-extracts'), '/\\')
             . DIRECTORY_SEPARATOR . $reportId;
    }

    public function crashReportThumbDir(int $projectId, int $reportId): string
    {
        // Same story for thumbs - keep generated thumbnails in the new
        // app's runtime so we don't muddy the legacy data dir.
        return rtrim(Yii::getAlias('@runtime/legacy-thumbs'), '/\\')
             . DIRECTORY_SEPARATOR . $reportId;
    }

    public function bugAttachmentPath(int $projectId, int $attachmentId, string $filename): string
    {
        $md5 = $this->lookupBugAttachmentMd5($attachmentId);
        return $this->md5ShardedPath('bugAttachments', $md5, '');
    }

    /**
     * Legacy debug-info layout is keyed by (filename, guid) under
     * data/debugInfo/<filename>/<guid>/<filename>. We need to look the
     * record up by id to read filename + guid + status.
     */
    public function debugInfoPath(int $projectId, int $debugInfoId, string $filename): string
    {
        $row = $this->lookupDebugInfo($debugInfoId);
        $name = $row['filename'] ?? $filename;
        $guid = $row['guid']     ?? '';

        $base = ((int) ($row['status'] ?? 0) <= 2)
            ? rtrim($this->getBaseDir(), '/\\') . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'debugInfo'
            : rtrim($this->getBaseDir(), '/\\') . DIRECTORY_SEPARATOR . 'debugInfo';

        // Strip any slashes from filename for safety; the legacy code
        // trusted DB content but we don't.
        $safe = basename($name);
        return $base . DIRECTORY_SEPARATOR . $safe . DIRECTORY_SEPARATOR . $guid . DIRECTORY_SEPARATOR . $safe;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Build "$root/$bucket/$shard1/$shard2/$md5$ext" the way Yii1 did.
     */
    protected function md5ShardedPath(string $bucket, string $md5, string $ext): string
    {
        if (strlen($md5) !== 32) {
            // Fall back to a deterministic dummy path so callers see a
            // definitely-not-found file rather than throwing.
            $md5 = str_repeat('0', 32);
        }
        $shard1 = substr($md5, 0, 3);
        $shard2 = substr($md5, 3, 3);
        return rtrim($this->getBaseDir(), '/\\')
             . DIRECTORY_SEPARATOR . $bucket
             . DIRECTORY_SEPARATOR . $shard1
             . DIRECTORY_SEPARATOR . $shard2
             . DIRECTORY_SEPARATOR . $md5 . $ext;
    }

    /**
     * @return string md5 hex (32 chars) or a no-match sentinel.
     */
    protected function lookupCrashReportMd5(int $id): string
    {
        $md5 = (new \yii\db\Query())
            ->select('md5')
            ->from('{{%crashreport}}')
            ->where(['id' => $id])
            ->scalar(Yii::$app->db);
        return is_string($md5) ? $md5 : str_repeat('0', 32);
    }

    /**
     * @return string md5 hex (32 chars) or a no-match sentinel.
     */
    protected function lookupBugAttachmentMd5(int $id): string
    {
        $md5 = (new \yii\db\Query())
            ->select('md5')
            ->from('{{%bug_attachment}}')
            ->where(['id' => $id])
            ->scalar(Yii::$app->db);
        return is_string($md5) ? $md5 : str_repeat('0', 32);
    }

    /**
     * @return array{filename:string,guid:string,status:int}|array{}
     */
    protected function lookupDebugInfo(int $id): array
    {
        $row = (new \yii\db\Query())
            ->select(['filename', 'guid', 'status'])
            ->from('{{%debuginfo}}')
            ->where(['id' => $id])
            ->one(Yii::$app->db);
        return is_array($row) ? $row : [];
    }
}
