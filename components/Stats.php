<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\db\Expression;
use yii\db\Query;
use app\models\Bug;
use app\models\Crashreport;
use app\models\Debuginfo;

/**
 * Aggregation queries powering the Digest charts.
 *
 * Each method returns a primitive `array` shaped for direct
 * `JsonResponse` consumption by the frontend Chart.js code:
 *
 *   ['labels' => [...], 'datasets' => [['label' => '...', 'data' => [...]], ...]]
 *
 * Project / appversion scoping is the caller's responsibility – the
 * controllers all run inside an AccessControl that forbids cross-project
 * snooping, so this component trusts its arguments.
 */
class Stats extends Component
{
    /**
     * Crash report upload counts grouped per day across the trailing
     * `$days` window (inclusive of today). Empty days are filled with
     * zeroes so the line chart has no holes.
     *
     * @return array{labels:string[],datasets:array<int,array{label:string,data:int[]}>}
     */
    public function crashReportUploadDynamics(int $projectId, int $days = 7, int $appversionId = -1): array
    {
        $today  = strtotime(date('Y-m-d'));
        $oldest = $today - ($days - 1) * 86400;

        $query = (new Query())
            ->select([
                'bucket' => new Expression('FROM_UNIXTIME(received, "%Y-%m-%d")'),
                'cnt'    => new Expression('COUNT(*)'),
            ])
            ->from(Crashreport::tableName())
            ->where(['project_id' => $projectId])
            ->andWhere(['>=', 'received', $oldest])
            ->groupBy(['bucket']);
        if ($appversionId !== -1) {
            $query->andWhere(['appversion_id' => $appversionId]);
        }
        $rows = $query->all(Yii::$app->db);

        return $this->fillDailyBuckets($rows, $oldest, $days, [
            ['label' => 'Reports', 'key' => 'cnt'],
        ]);
    }

    /**
     * Crash report counts grouped per app version.
     *
     * @return array{labels:string[],datasets:array<int,array<string,mixed>>}
     */
    public function crashReportVersionDistribution(int $projectId): array
    {
        $rows = (new Query())
            ->select([
                'version' => 'COALESCE(av.version, "(unknown)")',
                'cnt'     => new Expression('COUNT(*)'),
            ])
            ->from(['cr' => Crashreport::tableName()])
            ->leftJoin(['av' => '{{%appversion}}'], 'av.id = cr.appversion_id')
            ->where(['cr.project_id' => $projectId])
            ->groupBy(['version'])
            ->orderBy(['cnt' => SORT_DESC])
            ->limit(15)
            ->all(Yii::$app->db);

        return [
            'labels'   => array_column($rows, 'version'),
            'datasets' => [[
                'label' => 'Reports',
                'data'  => array_map('intval', array_column($rows, 'cnt')),
            ]],
        ];
    }

    /**
     * Crash report counts grouped per OS string.
     */
    public function crashReportOsDistribution(int $projectId, int $appversionId = -1): array
    {
        $q = (new Query())
            ->select([
                'os'  => 'COALESCE(NULLIF(os_ver_mdmp, ""), os_name_reg, "(unknown)")',
                'cnt' => new Expression('COUNT(*)'),
            ])
            ->from(Crashreport::tableName())
            ->where(['project_id' => $projectId])
            ->groupBy(['os'])
            ->orderBy(['cnt' => SORT_DESC])
            ->limit(15);

        if ($appversionId !== -1) {
            $q->andWhere(['appversion_id' => $appversionId]);
        }
        $rows = $q->all(Yii::$app->db);

        return [
            'labels'   => array_column($rows, 'os'),
            'datasets' => [[
                'label' => 'Reports',
                'data'  => array_map('intval', array_column($rows, 'cnt')),
            ]],
        ];
    }

    /**
     * Crash report counts grouped per geo location (ISO country code).
     * Returns the human-readable country name as label where known.
     */
    public function crashReportGeoDistribution(int $projectId, int $appversionId = -1): array
    {
        $q = (new Query())
            ->select([
                'geo' => 'COALESCE(NULLIF(geo_location, ""), "(unknown)")',
                'cnt' => new Expression('COUNT(*)'),
            ])
            ->from(Crashreport::tableName())
            ->where(['project_id' => $projectId])
            ->groupBy(['geo'])
            ->orderBy(['cnt' => SORT_DESC])
            ->limit(20);

        if ($appversionId !== -1) {
            $q->andWhere(['appversion_id' => $appversionId]);
        }
        $rows = $q->all(Yii::$app->db);

        $labels = array_map(
            fn($r) => Crashreport::geoIdToCountryName((string) $r['geo']),
            $rows
        );

        return [
            'labels'   => $labels,
            'datasets' => [[
                'label' => 'Reports',
                'data'  => array_map('intval', array_column($rows, 'cnt')),
            ]],
        ];
    }

    /**
     * Bug status changes per day across the trailing `$days` window.
     * Two series: opened, closed.
     */
    public function bugStatusDynamics(int $projectId, int $days = 7, int $appversionId = -1): array
    {
        $today  = strtotime(date('Y-m-d'));
        $oldest = $today - ($days - 1) * 86400;

        $opened = (new Query())
            ->select([
                'bucket' => new Expression('FROM_UNIXTIME(date_created, "%Y-%m-%d")'),
                'cnt'    => new Expression('COUNT(*)'),
            ])
            ->from(Bug::tableName())
            ->where(['project_id' => $projectId])
            ->andWhere(['>=', 'date_created', $oldest])
            ->groupBy(['bucket']);

        $closed = (new Query())
            ->select([
                'bucket' => new Expression('FROM_UNIXTIME(date_closed, "%Y-%m-%d")'),
                'cnt'    => new Expression('COUNT(*)'),
            ])
            ->from(Bug::tableName())
            ->where(['project_id' => $projectId])
            ->andWhere(['IS NOT', 'date_closed', null])
            ->andWhere(['>=', 'date_closed', $oldest])
            ->groupBy(['bucket']);

        if ($appversionId !== -1) {
            $opened->andWhere(['appversion_id' => $appversionId]);
            $closed->andWhere(['appversion_id' => $appversionId]);
        }

        return $this->fillDailyBucketsMulti(
            $oldest,
            $days,
            [
                ['label' => 'Opened', 'rows' => $opened->all(Yii::$app->db)],
                ['label' => 'Closed', 'rows' => $closed->all(Yii::$app->db)],
            ]
        );
    }

    /**
     * Open vs. closed bug counts (for the doughnut chart).
     */
    public function bugStatusDistribution(int $projectId, int $appversionId = -1): array
    {
        $q = (new Query())
            ->select([
                'kind' => new Expression('CASE WHEN status < ' . Bug::STATUS_OPEN_MAX . ' THEN "Open" ELSE "Closed" END'),
                'cnt'  => new Expression('COUNT(*)'),
            ])
            ->from(Bug::tableName())
            ->where(['project_id' => $projectId])
            ->groupBy(['kind']);

        if ($appversionId !== -1) {
            $q->andWhere(['appversion_id' => $appversionId]);
        }

        $rows = $q->all(Yii::$app->db);
        $byKind = ['Open' => 0, 'Closed' => 0];
        foreach ($rows as $r) {
            $byKind[$r['kind']] = (int) $r['cnt'];
        }

        return [
            'labels'   => array_keys($byKind),
            'datasets' => [[
                'label' => 'Bugs',
                'data'  => array_values($byKind),
            ]],
        ];
    }

    /**
     * Debug-info upload counts per day across the trailing $days window.
     */
    public function debugInfoUploadDynamics(int $projectId, int $days = 7): array
    {
        $today  = strtotime(date('Y-m-d'));
        $oldest = $today - ($days - 1) * 86400;

        $rows = (new Query())
            ->select([
                'bucket' => new Expression('FROM_UNIXTIME(dateuploaded, "%Y-%m-%d")'),
                'cnt'    => new Expression('COUNT(*)'),
            ])
            ->from(Debuginfo::tableName())
            ->where(['project_id' => $projectId])
            ->andWhere(['>=', 'dateuploaded', $oldest])
            ->groupBy(['bucket'])
            ->all(Yii::$app->db);

        return $this->fillDailyBuckets($rows, $oldest, $days, [
            ['label' => 'Files', 'key' => 'cnt'],
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Fill missing days with zeroes so a Chart.js line chart has no gaps.
     *
     * @param array<int,array<string,mixed>> $rows  rows with at least a 'bucket' key
     * @param array<int,array{label:string,key:string}> $datasets
     * @return array{labels:string[],datasets:array<int,array{label:string,data:int[]}>}
     */
    protected function fillDailyBuckets(array $rows, int $oldest, int $days, array $datasets): array
    {
        $byBucket = [];
        foreach ($rows as $r) {
            $byBucket[(string) $r['bucket']] = $r;
        }

        $labels = [];
        $data   = array_fill(0, count($datasets), []);

        for ($i = 0; $i < $days; $i++) {
            $ts     = $oldest + $i * 86400;
            $bucket = date('Y-m-d', $ts);
            $labels[] = $bucket;

            foreach ($datasets as $idx => $ds) {
                $data[$idx][] = isset($byBucket[$bucket]) ? (int) $byBucket[$bucket][$ds['key']] : 0;
            }
        }

        $sets = [];
        foreach ($datasets as $idx => $ds) {
            $sets[] = ['label' => $ds['label'], 'data' => $data[$idx]];
        }
        return ['labels' => $labels, 'datasets' => $sets];
    }

    /**
     * Multi-series version of fillDailyBuckets where each series brings
     * its own row set (because they come from different queries).
     *
     * @param array<int,array{label:string,rows:array<int,array<string,mixed>>}> $series
     * @return array{labels:string[],datasets:array<int,array{label:string,data:int[]}>}
     */
    protected function fillDailyBucketsMulti(int $oldest, int $days, array $series): array
    {
        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = date('Y-m-d', $oldest + $i * 86400);
        }

        $datasets = [];
        foreach ($series as $s) {
            $byBucket = [];
            foreach ($s['rows'] as $r) {
                $byBucket[(string) $r['bucket']] = (int) $r['cnt'];
            }
            $data = [];
            foreach ($labels as $label) {
                $data[] = $byBucket[$label] ?? 0;
            }
            $datasets[] = ['label' => $s['label'], 'data' => $data];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }
}
