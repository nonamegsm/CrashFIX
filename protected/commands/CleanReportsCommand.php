<?php

class CleanReportsCommand extends CConsoleCommand
{
    public function actionOld($limit = 1000)
    {
        // 1 year ago in seconds
        $oneYearAgo = time() - 365 * 24 * 3600;

        // Build a query to find up to 1000 oldest CrashReports older than 1 year
        $criteria = new CDbCriteria();
        $criteria->condition = 'received < :cutoff';
        $criteria->params    = array(':cutoff' => $oneYearAgo);
        $criteria->order     = 'received ASC';  // oldest first
        $criteria->limit     = (int)$limit;

        // Fetch matching CrashReport models
        $oldReports = CrashReport::model()->findAll($criteria);
        if (empty($oldReports)) {
            echo "No old crash reports found.\n";
            return;
        }

        // Show how many we found
        $countFound = count($oldReports);
        echo "Found {$countFound} old crash reports.\n";

        // The oldest is the first in the array (since sorted ASC)
        $oldestReport = reset($oldReports);   // or $oldReports[0]
        $oldestTime   = $oldestReport->received;

        // Print the oldest date in a readable format
        // Adjust the format as needed (e.g. 'Y-m-d H:i:s')
        echo "Oldest report date: " . date('Y-m-d H:i:s', $oldestTime) . "\n";

        // Proceed with deletion
        echo "Deleting these reports...\n";

        $deletedCount = 0;
        foreach ($oldReports as $report) {
            // This triggers beforeDelete() which also removes linked files
            if ($report->delete()) {
                $deletedCount++;
            }
        }

        echo "Deleted {$deletedCount} old crash reports.\n";
    }
}
