<?php

namespace tests\unit\components;

use app\components\Daemon;
use Codeception\Test\Unit;

/**
 * The Daemon component must degrade gracefully when the actual daemon
 * is not running (the very common dev case). These tests assert it never
 * throws and always returns the documented sentinel values.
 */
class DaemonTest extends Unit
{
    private Daemon $daemon;

    protected function _before(): void
    {
        // Configured to a deliberately-closed port in config/test.php.
        $this->daemon = \Yii::$app->daemon;
    }

    public function testGetDaemonStatusReturnsErrorWhenUnreachable(): void
    {
        $resp = '';
        $rc = $this->daemon->getDaemonStatus($resp);
        verify($rc)->lessThan(0);
        verify($resp)->notEmpty();
    }

    public function testGetConfigInfoReturnsStubWithErrorKey(): void
    {
        $info = $this->daemon->getConfigInfo();
        verify($info)->arrayHasKey('DaemonVer');
        verify($info)->arrayHasKey('_error');
        verify($info['DaemonVer'])->equals('unavailable');
    }

    public function testGetLicenseInfoReturnsStubWithErrorKey(): void
    {
        $info = $this->daemon->getLicenseInfo();
        verify($info)->arrayHasKey('LicenseType');
        verify($info)->arrayHasKey('_error');
    }

    public function testCheckDaemonReportsInactiveOrExtensionMissing(): void
    {
        $real = false;
        $err  = '';
        $code = $this->daemon->checkDaemon($real, $err);
        verify($code)->notEquals(Daemon::DAEMON_CHECK_OK);
        verify(in_array($code, [
            Daemon::DAEMON_CHECK_INACTIVE,
            Daemon::DAEMON_CHECK_EXTENSION_MISSING,
            Daemon::DAEMON_CHECK_STATUS_ERROR,
        ], true))->true();
    }
}
