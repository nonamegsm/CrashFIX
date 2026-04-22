<?php

namespace tests\unit\components;

use app\components\Stats;
use Codeception\Test\Unit;

/**
 * Format-shape tests for the Stats component.
 *
 * Doesn't run real DB queries — instead invokes the protected bucket
 * helpers via reflection to lock down the JSON shape that Chart.js
 * depends on.
 */
class StatsTest extends Unit
{
    private Stats $stats;

    protected function _before(): void
    {
        $this->stats = new Stats();
    }

    public function testFillDailyBucketsFillsZeroes(): void
    {
        $today  = strtotime(date('Y-m-d'));
        $oldest = $today - 6 * 86400;

        $rows = [
            ['bucket' => date('Y-m-d', $today), 'cnt' => 5],
        ];

        $r = new \ReflectionClass(Stats::class);
        $m = $r->getMethod('fillDailyBuckets');
        $m->setAccessible(true);
        $out = $m->invoke($this->stats, $rows, $oldest, 7, [
            ['label' => 'Reports', 'key' => 'cnt'],
        ]);

        verify($out)->arrayHasKey('labels');
        verify($out)->arrayHasKey('datasets');
        verify(count($out['labels']))->equals(7);
        verify($out['datasets'][0]['label'])->equals('Reports');
        // Last day has 5, all others 0.
        verify(end($out['datasets'][0]['data']))->equals(5);
        verify(array_sum($out['datasets'][0]['data']))->equals(5);
    }

    public function testFillDailyBucketsMultiHandlesMultipleSeries(): void
    {
        $today  = strtotime(date('Y-m-d'));
        $oldest = $today - 2 * 86400; // 3 days

        $r = new \ReflectionClass(Stats::class);
        $m = $r->getMethod('fillDailyBucketsMulti');
        $m->setAccessible(true);
        $out = $m->invoke($this->stats, $oldest, 3, [
            ['label' => 'Opened', 'rows' => [['bucket' => date('Y-m-d', $today),     'cnt' => 3]]],
            ['label' => 'Closed', 'rows' => [['bucket' => date('Y-m-d', $today - 86400), 'cnt' => 1]]],
        ]);

        verify(count($out['labels']))->equals(3);
        verify(count($out['datasets']))->equals(2);
        verify($out['datasets'][0]['label'])->equals('Opened');
        verify($out['datasets'][1]['label'])->equals('Closed');
        verify(array_sum($out['datasets'][0]['data']))->equals(3);
        verify(array_sum($out['datasets'][1]['data']))->equals(1);
    }
}
