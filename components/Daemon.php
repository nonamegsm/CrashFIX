<?php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * Client for the CrashFix background daemon.
 *
 * The daemon is a separate Windows service / Linux process that listens
 * on a plain TCP socket (default 127.0.0.1:50). It speaks a simple,
 * line-oriented protocol mirrored from the legacy CrashFix Yii1 build:
 *
 *   server:  100 Accepting requests.\n
 *   client:  cmd1;cmd2;...;\n
 *   server:  <retcode> <body>\n
 *
 * This class is deliberately defensive: when the daemon cannot be
 * reached (a very common case during web-only development) every public
 * method returns a fail-soft value rather than throwing, so the
 * authenticated parts of the UI keep rendering.
 */
class Daemon extends Component
{
    public string $host        = '127.0.0.1';
    public int    $servicePort = 50;
    public int    $timeout     = 5;

    /** Last raw response captured for debugging. */
    public string $errorMsg = '';

    // Public status codes mirrored from legacy CrashFix.
    const DAEMON_CHECK_OK                = 0;
    const DAEMON_CHECK_INACTIVE          = 1;
    const DAEMON_CHECK_EXTENSION_MISSING = 2;
    const DAEMON_CHECK_CONFIG_ERROR      = 3;
    const DAEMON_CHECK_VER_MISMATCH      = 4;
    const DAEMON_CHECK_BAD_WEB_ROOT_DIR  = 5;
    const DAEMON_CHECK_STATUS_ERROR      = 6;

    /**
     * Cached "is the daemon alive?" status. Throttles real socket
     * checks to once every five minutes per session to avoid
     * hammering the daemon on every page load.
     */
    public function checkDaemon(bool &$realCheck, string &$errorMsg): int
    {
        $session = Yii::$app->session;
        $last    = (int) ($session['lastDaemonCheckTime']   ?? 0);
        $lastSt  = (int) ($session['lastDaemonCheckStatus'] ?? self::DAEMON_CHECK_OK);

        if ($last && (time() - $last) <= 300) {
            $realCheck = false;
            $errorMsg  = '';
            return $lastSt;
        }

        $realCheck = true;
        $errorMsg  = '';
        $code      = self::DAEMON_CHECK_OK;

        if (!extension_loaded('sockets')) {
            $code = self::DAEMON_CHECK_EXTENSION_MISSING;
            $errorMsg = 'PHP sockets extension is disabled.';
        } else {
            $resp = '';
            $rc   = $this->getDaemonStatus($resp);
            if ($rc < 0) {
                $code = self::DAEMON_CHECK_INACTIVE;
                $errorMsg = $resp ?: 'Daemon is not responding.';
            } elseif ($rc !== 0) {
                $code = self::DAEMON_CHECK_STATUS_ERROR;
                $errorMsg = $resp;
            }
        }

        $session['lastDaemonCheckTime']   = time();
        $session['lastDaemonCheckStatus'] = $code;
        return $code;
    }

    /**
     * Send `daemon status` and return the raw response in $response.
     * Returns the daemon's reply code, or -1 on transport error.
     */
    public function getDaemonStatus(string &$response): int
    {
        return $this->execCommand('daemon status', $response);
    }

    /**
     * Returns associative info about the running daemon
     * (DaemonVer, WebRootDir, Uptime, ProcessId, ...). Falls back to a
     * static stub when the daemon is unreachable so callers can still
     * render a placeholder admin panel.
     *
     * @return array<string,string>
     */
    public function getConfigInfo(): array
    {
        $tmp = tempnam(Yii::$app->getRuntimePath(), 'ci');
        $resp = '';
        $rc = $this->execCommand('daemon get-config-info "' . $tmp . '"', $resp);

        if ($rc !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return $this->stubConfigInfo($resp);
        }

        try {
            $doc = @simplexml_load_file($tmp);
            if ($doc === false || $doc === null || !isset($doc->Daemon)) {
                return $this->stubConfigInfo('Invalid XML from daemon.');
            }
            $g = $doc->Daemon;
            return [
                'DaemonVer'  => (string) ($g->DaemonVer  ?? ''),
                'WebRootDir' => (string) ($g->WebRootDir ?? ''),
                'Uptime'     => (string) ($g->Uptime     ?? ''),
                'ProcessId'  => (string) ($g->ProcessId  ?? ''),
            ];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Returns the parsed license-info document, or a stub when the
     * daemon is unreachable.
     *
     * @return array<string,string>
     */
    public function getLicenseInfo(): array
    {
        $tmp = tempnam(Yii::$app->getRuntimePath(), 'li');
        $resp = '';
        $rc = $this->execCommand('daemon get-license-info "' . $tmp . '"', $resp);

        if ($rc !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return $this->stubLicenseInfo($resp);
        }

        try {
            $doc = @simplexml_load_file($tmp);
            if ($doc === false || $doc === null || !isset($doc->General)) {
                return $this->stubLicenseInfo('Invalid XML from daemon.');
            }
            $g = $doc->General;
            return [
                'DateCreated'  => isset($g->DateCreated) ? date('d M Y', strtotime((string) $g->DateCreated)) : '',
                'LicenseType'  => (string) ($g->LicenseType  ?? 'unknown'),
                'LicensedTo'   => (string) ($g->LicensedTo   ?? ''),
                'ExpiresAt'    => (string) ($g->ExpiresAt    ?? ''),
                'MaxProjects'  => (string) ($g->MaxProjects  ?? ''),
                'MaxUsers'     => (string) ($g->MaxUsers     ?? ''),
            ];
        } finally {
            @unlink($tmp);
        }
    }

    // ------------------------------------------------------------------
    // Low-level transport
    // ------------------------------------------------------------------

    /**
     * Execute a single line of daemon command. Returns the daemon's
     * reply code (0 == OK, negative == transport error).
     */
    public function execCommand(string $command, string &$response): int
    {
        return $this->execCommands([$command], $response);
    }

    /**
     * Execute several commands in a single round-trip.
     *
     * @param string[] $commands
     */
    public function execCommands(array $commands, string &$response): int
    {
        $request = implode(';', $commands) . ';' . "\n";
        return $this->sendRequest($request, $response);
    }

    /**
     * Open a TCP socket to the daemon, send the request, drain the
     * response, and parse the leading "<code> <body>" wire format.
     */
    private function sendRequest(string $request, string &$response): int
    {
        $response = '';
        $this->errorMsg = '';

        if (!extension_loaded('sockets')) {
            $this->errorMsg = 'PHP sockets extension is disabled.';
            $response = $this->errorMsg;
            return -1;
        }

        $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock === false) {
            $this->errorMsg = 'Could not create socket: ' . socket_strerror(socket_last_error());
            $response = $this->errorMsg;
            return -1;
        }

        @socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        @socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        if (@socket_connect($sock, $this->host, $this->servicePort) === false) {
            $this->errorMsg = 'Could not connect to daemon at ' . $this->host . ':' . $this->servicePort
                            . ' - ' . socket_strerror(socket_last_error($sock));
            $response = $this->errorMsg;
            socket_close($sock);
            return -1;
        }

        // Greeting
        $greeting = @socket_read($sock, 128);
        if ($greeting !== "100 Accepting requests.\n") {
            $this->errorMsg = 'Unexpected greeting from daemon: ' . trim((string) $greeting);
            socket_close($sock);
            $response = $this->errorMsg;
            return -1;
        }

        @socket_write($sock, $request, strlen($request));

        $body = '';
        while (($buf = @socket_read($sock, 2048)) !== false && $buf !== '') {
            $body .= $buf;
        }
        socket_close($sock);

        // Parse "<code> <body>"
        if (preg_match('/^(-?\d+)\s+(.*)$/s', $body, $m)) {
            $response = trim($m[2]);
            return (int) $m[1];
        }

        $response = trim($body);
        return -1;
    }

    /**
     * @return array<string,string>
     */
    private function stubConfigInfo(string $reason): array
    {
        return [
            'DaemonVer'  => 'unavailable',
            'WebRootDir' => '',
            'Uptime'     => '',
            'ProcessId'  => '',
            '_error'     => $reason ?: 'Daemon unreachable.',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function stubLicenseInfo(string $reason): array
    {
        return [
            'LicenseType' => 'Free / Unknown',
            'DateCreated' => '',
            'LicensedTo'  => '',
            'ExpiresAt'   => '',
            'MaxProjects' => '',
            'MaxUsers'    => '',
            '_error'      => $reason ?: 'Daemon unreachable.',
        ];
    }
}
