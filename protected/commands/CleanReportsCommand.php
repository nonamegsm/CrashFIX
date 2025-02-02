<?php

class CleanReportsCommand extends CConsoleCommand
{
    // Adjust if needed – must be the same partition
    // that stores your "data/crashReports" folder.
    // If you store crash reports somewhere else, change the path accordingly.
    protected $reportDataPath;

    public function init()
    {
        // Example path: /path/to/protected/data/crashReports
        // (Assumes "crashReports" is inside "protected/data")
        $this->reportDataPath = Yii::app()->basePath . '/data/crashReports';
        parent::init();
    }

    /**
     * Deletes up to $limit CrashReport records older than one year,
     * measuring disk space usage before & after.
     *
     * Example usage:
     *   php yiic cleanReports old --limit=1000
     *
     * @param int $limit Maximum number of reports to delete in one pass.
     */
    public function actionOld($limit = 1000)
    {
        // 1 year in seconds
        $oneYearAgo = time() - 365 * 24 * 3600;

        // Build a query to find up to 1000 oldest CrashReports older than 1 year
        $criteria = new CDbCriteria();
        $criteria->condition = 'received < :cutoff';
        $criteria->params    = array(':cutoff' => $oneYearAgo);
        $criteria->order     = 'received ASC';  // oldest first
        $criteria->limit     = (int)$limit;

        // -- 1) Measure free disk space before
        // Ensure $this->reportDataPath points to the same partition
        // that actually stores your .zip crash report files.
        $freeBeforeBytes = @disk_free_space($this->reportDataPath);
        if ($freeBeforeBytes === false) {
            echo "Warning: Could not determine disk space at '{$this->reportDataPath}'.\n";
            $freeBeforeBytes = 0; // fallback
        }

        // -- 2) Fetch matching CrashReport models
        $oldReports = CrashReport::model()->findAll($criteria);
        if (empty($oldReports)) {
            echo "No old crash reports found.\n";
            return;
        }

        $countFound = count($oldReports);
        echo "Found {$countFound} old crash reports.\n";

        // For reference, see the oldest one’s date
        $oldestReport = reset($oldReports);
        echo "Oldest report date: " . date('Y-m-d H:i:s', $oldestReport->received) . "\n";
        echo "Deleting...\n";

        // -- 3) Delete each one (->delete() will also remove associated files)
        $deletedCount = 0;
        foreach ($oldReports as $report) {
            if ($report->delete()) {
                $deletedCount++;
            }
        }

        // -- 4) Measure free disk space after
        $freeAfterBytes = @disk_free_space($this->reportDataPath);
        if ($freeAfterBytes === false) {
            echo "Warning: Could not determine new disk space at '{$this->reportDataPath}'.\n";
            $freeAfterBytes = 0; // fallback
        }

        // -- 5) Calculate difference in KB
        $deltaBytes = $freeAfterBytes - $freeBeforeBytes;
        $deltaKB = $deltaBytes / 1024.0;

        echo "Deleted {$deletedCount} old crash reports.\n";

        // If on the same partition and no other processes wrote or freed disk
        // space concurrently, 'deltaKB' is how many KB were freed.
        echo sprintf(
            "Free disk space before: %.2f KB, after: %.2f KB, delta: %.2f KB\n",
            $freeBeforeBytes / 1024.0,
            $freeAfterBytes / 1024.0,
            $deltaKB
        );
    }
	
	
	    /**
     * Scans for orphaned .zip files (no matching CrashReport) and deletes up to $limit.
     * 
     * Usage:
     *   php yiic cleanReports clearOrphans --limit=3000
     *
     * @param int $limit max orphaned files to remove in one pass
     */
    public function actionClearOrphans($limit = 3000)
    {
        echo "Scanning for orphaned .zip files in: {$this->reportDataPath}\n";

        // Measure free disk space before
        $freeBefore = $this->getFreeSpace($this->reportDataPath);

        // Get up to $limit orphan .zip files
        $orphanFiles = $this->findOrphanedZipFiles($this->reportDataPath, $limit);
        $countOrphans = count($orphanFiles);

        if ($countOrphans === 0) {
            echo "No orphaned files found (or limit exhausted).\n";
            return;
        }

        echo "Found {$countOrphans} orphaned file(s). Deleting...\n";
        $deletedCount = 0;
        $totalBytesFreed = 0;

        // Delete each orphan .zip
        foreach ($orphanFiles as $filepath) {
            $size = filesize($filepath);
            if (@unlink($filepath)) {
                $deletedCount++;
                $totalBytesFreed += $size;
                // Optionally remove empty directories up the chain
                $this->removeEmptyDirs(dirname($filepath));
            }
        }

        // measure free disk space after
        $freeAfter = $this->getFreeSpace($this->reportDataPath);

        echo "Deleted {$deletedCount} orphaned file(s).\n";
        echo sprintf("Freed ~%.2f KB (sum of file sizes).\n", $totalBytesFreed / 1024.0);

        if ($freeBefore >= 0 && $freeAfter >= 0) {
            $deltaKB = ($freeAfter - $freeBefore) / 1024;
            echo sprintf(
                "Disk space before: %.2f KB, after: %.2f KB, delta: %.2f KB.\n",
                $freeBefore / 1024.0,
                $freeAfter / 1024.0,
                $deltaKB
            );
        }
    }

    /**
     * Gathers up to $limit orphaned .zip files from $startDir.
     * 
     * @param string $startDir Folder to scan recursively
     * @param int $limit how many orphan files to collect
     * @return string[] Array of absolute file paths to orphaned .zip files
     */
    protected function findOrphanedZipFiles($startDir, $limit = 3000)
    {
        $results = [];
        $foundCount = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($startDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileinfo) {
            if ($foundCount >= $limit) {
                // We reached our limit for this pass; stop searching more
                break;
            }

            if ($fileinfo->isFile()) {
                $filename = $fileinfo->getFilename();
                // Check if it ends with ".zip"
                if (strtolower(substr($filename, -4)) === '.zip') {
                    // Extract the part before ".zip"
                    $base = substr($filename, 0, -4);
                    // Is $base a 32-char hex string?
                    if (preg_match('/^[0-9a-fA-F]{32}$/', $base)) {
                        // Check DB for CrashReport with md5=$base
                        $hasReport = CrashReport::model()->exists('md5=:md5', [':md5' => $base]);
                        if (!$hasReport) {
                            $results[] = $fileinfo->getPathname();
                            $foundCount++;
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Removes empty directories upward, stopping if not empty or root is reached.
     */
    protected function removeEmptyDirs($dir)
    {
        // Attempt to remove if empty
        if (@rmdir($dir)) {
            // If removal succeeded, check parent
            $parent = dirname($dir);
            // Avoid climbing above our base path
            if (strlen($parent) >= strlen($this->reportDataPath)) {
                $this->removeEmptyDirs($parent);
            }
        }
    }

    /**
     * Helper to safely get disk free space (in bytes) on the partition containing $path.
     * Returns -1 if it fails.
     */
    protected function getFreeSpace($path)
    {
        $val = @disk_free_space($path);
        return ($val === false) ? -1 : $val;
    }
	
	
}
