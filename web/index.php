<?php

// Legacy compatibility shim: this repository currently runs Yii1 from /index.php.
// If /web/index.php is requested directly, redirect to the equivalent non-/web path.
$requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
$targetUri = preg_replace('#/web(/|$)#', '/', $requestUri, 1);

if (!is_string($targetUri) || $targetUri === '') {
    $targetUri = '/';
}

if ($targetUri !== $requestUri) {
    header('Location: ' . $targetUri, true, 302);
    exit;
}

require dirname(__DIR__) . '/index.php';
