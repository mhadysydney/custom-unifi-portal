<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Portal\Database;
use Portal\CookieManager;
use Portal\UniFiHelper;

$config = require __DIR__ . '/../config/sites.php';
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$hostMap = [
    'portal-kenien.satgroupe.com' => 'site1',
    'portal-knia.satgroupe.com'     => 'site2',
    'portal-lbr.satgroupe.com'     => 'site3',
];

$siteKey = $hostMap[$host] ?? $config['default_site'];
$helper = new UniFiHelper($config, $siteKey);

$mac       = strtolower($_POST['mac'] ?? '');
$apMac     = $_POST['ap'] ?? null;
$url       = $_POST['url'] ?? 'https://www.google.com';

$code      = trim($_POST['voucher'] ?? '');

if (!$mac || !$code || !isset($config['sites'][$siteKey])) {
    header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&error=invalid');
    exit;
}

try {
    
    $voucher = $helper->findVoucher($code);

    if (!$voucher) {
        header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&ap=' . urlencode($apMac ?? '') . '&url=' . urlencode($url) . '&error=badcode');
        exit;
    }

    $minutes = (int)($voucher['duration'] ?? $voucher['time_limit'] ?? 60);
    if ($minutes < 1) $minutes = 60;

    if (!$helper->authorize($mac, $minutes, $apMac)) {
        throw new Exception('Authorization failed');
    }

    // Cookie anti-rotation
    $token = CookieManager::generateToken();
    CookieManager::set($token);
    $db->saveSession($siteKey, $mac, $code, $minutes, $token);

    header('Location: success.php?url=' . urlencode($url));
    exit;

} catch (Exception $e) {
    $helper->log('AUTH ERROR', [
        'error' => $e->getMessage(),
        'class' => get_class($e),
        'site'  => $siteKey,
        'mac'   => $mac,
        'apMac' => $apMac,
        'url'   => $url,
        'code'  => $code,
    ]);
    /*@file_put_contents(
        __DIR__ . '/../data/portal_debug.log',
        date('c') . ' AUTH ERROR: ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );*/
    header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&error=server');
    exit;
}