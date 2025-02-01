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
}
