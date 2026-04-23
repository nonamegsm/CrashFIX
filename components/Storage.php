<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
use ZipArchive;

/**
 * Storage abstraction for CrashFix uploaded artefacts.
 *
 * All on-disk reads and writes for crash reports, debug symbols and bug
 * attachments are funneled through this single component so the rest of
 * the application never has to care about the directory layout, the
 * temp-file/rename atomic-write dance, or path-traversal hardening.
 *
 * Layout (under {@see $basePath}):
 *
 *   projects/{project_id}/crashreports/{report_id}.zip
 *   projects/{project_id}/crashreports_extracted/{report_id}/...
 *   projects/{project_id}/thumbs/{report_id}/{filename}
 *   projects/{project_id}/bug_attachments/{attachment_id}_{filename}
 *   projects/{project_id}/debuginfo/{debuginfo_id}_{filename}
 *
 * Configure via {@see web.php} components:
 *   'storage' => ['class' => 'app\components\Storage', 'basePath' => '@app/data']
 */
class Storage extends Component
{
    /**
     * Aliased filesystem root. Safe to be inside or outside the document root.
     * Default `@app/data` keeps it outside web/ to prevent direct download.
     */
    public string $basePath = '@app/data';

    /** Permissions for newly created directories. */
    public int $dirMode = 0775;

    /** Default JPEG/PNG thumbnail size (max edge in pixels). */
    public int $thumbMaxEdge = 220;

    /**
     * Returns the resolved on-disk root, ensuring it exists.
     */
    public function getBaseDir(): string
    {
        $dir = (string) Yii::getAlias($this->basePath);
        if (!is_dir($dir)) {
            $this->mkdirRecursive($dir);
        }
        return $dir;
    }

    // ------------------------------------------------------------------
    // Path builders (do NOT touch disk)
    // ------------------------------------------------------------------

    public function crashReportPath(int $projectId, int $reportId): string
    {
        return $this->safeJoin([
            'projects', (string) $projectId, 'crashreports', $reportId . '.zip',
        ]);
    }

    public function crashReportExtractDir(int $projectId, int $reportId): string
    {
        return $this->safeJoin([
            'projects', (string) $projectId, 'crashreports_extracted', (string) $reportId,
        ]);
    }

    public function crashReportThumbDir(int $projectId, int $reportId): string
    {
        return $this->safeJoin([
            'projects', (string) $projectId, 'thumbs', (string) $reportId,
        ]);
    }

    public function bugAttachmentPath(int $projectId, int $attachmentId, string $filename): string
    {
        $clean = $this->sanitiseLeaf($filename);
        return $this->safeJoin([
            'projects', (string) $projectId, 'bug_attachments', $attachmentId . '_' . $clean,
        ]);
    }

    public function debugInfoPath(int $projectId, int $debugInfoId, string $filename): string
    {
        $clean = $this->sanitiseLeaf($filename);
        return $this->safeJoin([
            'projects', (string) $projectId, 'debuginfo', $debugInfoId . '_' . $clean,
        ]);
    }

    /**
     * Working directory for an "extra files" ZIP build (mirrors legacy
     * protected/data/extraFiles/{name}_{id}/).
     */
    public function extraFilesCollectionWorkDir(string $collectionName, int $collectionId): string
    {
        $leaf = $this->sanitiseExtraFilesBaseName($collectionName) . '_' . $collectionId;
        return $this->safeJoin(['extraFiles', $leaf]);
    }

    /**
     * Final ZIP path for an extra-files collection.
     */
    public function extraFilesCollectionZipPath(string $collectionName, int $collectionId): string
    {
        $leaf = $this->sanitiseExtraFilesBaseName($collectionName) . '_' . $collectionId . '.zip';
        return $this->safeJoin(['extraFiles', $leaf]);
    }

    // ------------------------------------------------------------------
    // Read / write primitives
    // ------------------------------------------------------------------

    /**
     * Move an UploadedFile (or any temp file path) into permanent storage.
     * Performs an atomic write via tempfile + rename to avoid half-written
     * files appearing if the request is interrupted.
     */
    public function writeUploadedFile(string $tempPath, string $destinationPath): void
    {
        $this->mkdirRecursive(dirname($destinationPath));

        $tmp = $destinationPath . '.tmp.' . bin2hex(random_bytes(4));

        if (!@copy($tempPath, $tmp)) {
            throw new \RuntimeException("Could not copy uploaded file to staging path {$tmp}.");
        }
        if (!@rename($tmp, $destinationPath)) {
            @unlink($tmp);
            throw new \RuntimeException("Could not move staged file into final destination {$destinationPath}.");
        }
    }

    /**
     * Stream a file to the browser as a download. Sets Content-Type,
     * Content-Length, Content-Disposition headers and exits.
     *
     * @param bool $forceDownload When false (e.g. for screenshots), serves
     *                            inline so browsers can render in-place.
     */
    public function streamDownload(string $absolutePath, ?string $sendAsName = null, bool $forceDownload = true): void
    {
        if (!is_file($absolutePath)) {
            throw new \yii\web\NotFoundHttpException('Stored file is missing on disk.');
        }

        $mime = $this->guessMime($absolutePath);
        $name = $sendAsName ?: basename($absolutePath);

        Yii::$app->response->sendFile($absolutePath, $name, [
            'mimeType'  => $mime,
            'inline'    => !$forceDownload,
        ])->send();
        Yii::$app->end();
    }

    /**
     * Extract a single named entry from a ZIP archive into a temp file
     * and return its absolute path. The caller is responsible for unlinking.
     *
     * @return string|null Path to extracted temp file, null if not found.
     */
    public function extractZipEntry(string $zipPath, string $entryName): ?string
    {
        if (!is_file($zipPath)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $stream = $zip->getStream($entryName);
        if ($stream === false) {
            $zip->close();
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'cfx_');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $zip->close();
        return $tmp;
    }

    /**
     * Returns a list of [filename => uncompressed_size] entries inside a
     * ZIP archive. Empty array on missing file or open failure.
     *
     * @return array<string,int>
     */
    public function listZipEntries(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat) {
                $entries[$stat['name']] = (int) $stat['size'];
            }
        }
        $zip->close();
        return $entries;
    }

    /**
     * Generate (or look up) a thumbnail for an image file inside the
     * report's extracted-file area. Falls back to streaming the original
     * image if GD is unavailable.
     */
    public function makeThumbnail(string $sourcePath, string $thumbPath): bool
    {
        if (!extension_loaded('gd')) {
            return @copy($sourcePath, $thumbPath);
        }

        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }
        [$srcW, $srcH, $type] = $info;

        $loader = match ($type) {
            IMAGETYPE_PNG  => 'imagecreatefrompng',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_GIF  => 'imagecreatefromgif',
            default        => null,
        };
        if ($loader === null || !function_exists($loader)) {
            return @copy($sourcePath, $thumbPath);
        }

        $src = $loader($sourcePath);
        if (!$src) {
            return false;
        }

        $scale = min(1, $this->thumbMaxEdge / max($srcW, $srcH));
        $dstW  = max(1, (int) ($srcW * $scale));
        $dstH  = max(1, (int) ($srcH * $scale));

        $dst = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $this->mkdirRecursive(dirname($thumbPath));
        $ok = match ($type) {
            IMAGETYPE_PNG  => imagepng($dst, $thumbPath),
            IMAGETYPE_JPEG => imagejpeg($dst, $thumbPath, 80),
            IMAGETYPE_GIF  => imagegif($dst, $thumbPath),
            default        => false,
        };

        imagedestroy($src);
        imagedestroy($dst);
        return (bool) $ok;
    }

    /**
     * Recursively delete a directory tree. No-op if the path does not exist.
     */
    public function rmdirRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    /**
     * Idempotent recursive mkdir.
     */
    public function mkdirRecursive(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, $this->dirMode, true) && !is_dir($path)) {
            throw new \RuntimeException("Could not create directory {$path}.");
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Build an absolute path inside the storage root from a list of
     * already-untrusted segments, rejecting `..` traversal.
     *
     * @param string[] $segments
     */
    protected function safeJoin(array $segments): string
    {
        foreach ($segments as $s) {
            if ($s === '' || $s === '.' || $s === '..' || str_contains($s, "\0")) {
                throw new InvalidArgumentException("Refusing unsafe storage path segment: " . var_export($s, true));
            }
        }
        return rtrim($this->getBaseDir(), '/\\') . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Reduce a user-supplied filename to a "safe leaf" usable on every
     * filesystem we care about.
     */
    protected function sanitiseLeaf(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._\-]/', '_', $name) ?? '';
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }
        return $name;
    }

    /**
     * Reduce a collection base name (project + date range) to a single
     * safe path segment for extraFiles/* storage.
     */
    protected function sanitiseExtraFilesBaseName(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = preg_replace('/[^A-Za-z0-9._\-]/', '_', $name) ?? '';
        if ($name === '' || $name === '.' || $name === '..') {
            return 'collection';
        }
        return $name;
    }

    /**
     * Best-effort MIME detection via fileinfo, with extension fallback.
     */
    protected function guessMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = @finfo_file($f, $path);
                @finfo_close($f);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'           => 'image/png',
            'jpg', 'jpeg'   => 'image/jpeg',
            'gif'           => 'image/gif',
            'ogg', 'ogv'    => 'video/ogg',
            'mp4'           => 'video/mp4',
            'webm'          => 'video/webm',
            'zip'           => 'application/zip',
            'pdb'           => 'application/octet-stream',
            'txt', 'log'    => 'text/plain; charset=utf-8',
            default         => 'application/octet-stream',
        };
    }
}
