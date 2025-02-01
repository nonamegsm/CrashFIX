<?php
/**
 * Console command to delete old CrashReport records (older than 1 year).
 */
class CleanReportsCommand extends CConsoleCommand
{
    /**
     * Example usage:
     *   php yiic cleanReports old --limit=1000
     *
     * @param integer $limit Max number of old reports to delete in one run.
     */
    public function actionOld($limit = 1000)
    {
        // Calculate the cutoff timestamp (1 year ago)
        $oneYearAgo = time() - 365 * 24 * 3600;

        // Build criteria to find the oldest CrashReports older than 1 year
        $criteria = new CDbCriteria();
        $criteria->condition = 'received < :cutoff';
        $criteria->params = array(':cutoff' => $oneYearAgo);
        $criteria->order = 'received ASC';  // oldest first
        $criteria->limit = (int) $limit;

        // Fetch matching CrashReport models
        $oldReports = CrashReport::model()->findAll($criteria);

        // If no old reports found, just exit
        if (empty($oldReports)) {
            echo "No old crash reports found.\n";
            return;
        }

        echo "Found ".count($oldReports)." old crash reports. Deleting...\n";

        // Delete each one
        $deletedCount = 0;
        foreach ($oldReports as $report) {
            // The model’s beforeDelete() etc. will handle cleanup
            if ($report->delete()) {
                $deletedCount++;
            }
        }

        echo "Deleted {$deletedCount} old crash reports.\n";
    }
}
