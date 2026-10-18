<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Portal\Database;
use Portal\CookieManager;
use Portal\UniFiHelper;

$config = require __DIR__ . '/../config/sites.php';
$db = new Database();

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');

$hostMap = [
    'portal-kenien.satgroupe.com' => 'site1',
    'portal-knia.satgroupe.com'     => 'site2',
    'portal-lbr.satgroupe.com'     => 'site3',
];

$siteKey = $hostMap[$host] ?? $config['default_site'];

// Paramètres UniFi
$mac     = strtolower($_GET['id'] ?? $_GET['mac'] ?? '');
$apMac   = $_GET['ap'] ?? null;
$url     = $_GET['url'] ?? 'https://www.google.com';
$ssid    = $_GET['ssid'] ?? '';

// Sécurité basique
if (!isset($config['sites'][$siteKey])) {
    $siteKey = $config['default_site'];
}

$site = $config['sites'][$siteKey];

// === Gestion cookie / MAC rotation ===
$token = CookieManager::get();
$autoAuthorized = false;

if ($token && $mac) {
    $session = $db->findByCookie($token);
    if ($session && $session['site_key'] === $siteKey && $session['expires_at'] > time()) {
        // MAC a changé → on ré-autorise la nouvelle
        if (strtolower($session['mac']) !== $mac) {
            try {
                $helper = new UniFiHelper($config, $siteKey);
                $remaining = max(1, (int)ceil(($session['expires_at'] - time()) / 60));
                if ($helper->authorize($mac, $remaining, $apMac)) {
                    $db->updateMac($token, $mac);
                    $autoAuthorized = true;
                }
            } catch (Exception $e) {
                // log si besoin
            }
        } else {
            // même MAC encore valide → on laisse passer
            $autoAuthorized = true;
        }
    }
}

if ($autoAuthorized) {
    header('Location: success.php?url=' . urlencode($url));
    exit;
}

$error = $_GET['error'] ?? null;

// Affiche la page
require __DIR__ . '/../templates/portal.php';