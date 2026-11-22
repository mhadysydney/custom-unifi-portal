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
    $helper->log('VOUCHER AUTH REJECTED', [
        'reason' => 'missing_mac_or_voucher',
        'site' => $siteKey,
        'mac' => $mac ?: null,
        'apMac' => $apMac,
    ]);
    header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&error=invalid');
    exit;
}

try {
    $helper->log('VOUCHER AUTH ATTEMPT', [
        'mac' => $mac,
        'apMac' => $apMac,
    ]);

    $voucher = $helper->findVoucher($code);

    if (!$voucher) {
        $helper->log('VOUCHER AUTH REJECTED', [
            'reason' => 'invalid_or_exhausted_voucher',
            'mac' => $mac,
            'apMac' => $apMac,
        ]);
        header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&ap=' . urlencode($apMac ?? '') . '&url=' . urlencode($url) . '&error=badcode');
        exit;
    }

    $minutes = (int)($voucher['duration'] ?? $voucher['time_limit'] ?? 60);
    if ($minutes < 1) $minutes = 60;
    $quota = (int)($voucher['quota'] ?? 0);
    $voucherId = (string)($voucher['_id'] ?? '');
    $voucherCode = preg_replace('/\D/', '', (string)($voucher['code'] ?? $code));
    $uploadKbps = (int)($voucher['qos_rate_max_up'] ?? $voucher['up'] ?? $voucher['rate_max_up'] ?? 0);
    $downloadKbps = (int)($voucher['qos_rate_max_down'] ?? $voucher['down'] ?? $voucher['rate_max_down'] ?? 0);
    $dataLimitMb = (int)($voucher['megabytes'] ?? $voucher['qos_usage_quota'] ?? 0);
    $voucherCreatedAt = (int)($voucher['create_time'] ?? 0);

    $token = CookieManager::get();
    $existingSession = $token ? $db->findByCookie($token) : null;
    $existingCode = $existingSession
        ? preg_replace('/\D/', '', (string)($existingSession['voucher_code'] ?? ''))
        : '';
    $existingSameVoucher = $existingSession
        && $existingSession['site_key'] === $siteKey
        && $existingCode === $voucherCode
        && (int)$existingSession['expires_at'] > time();

    if ($existingSameVoucher) {
        $remaining = max(1, (int)ceil(((int)$existingSession['expires_at'] - time()) / 60));
        if (!$helper->authorize($mac, $remaining, $apMac)) {
            throw new Exception('Authorization failed');
        }
        $db->updateMac($token, $mac);
        CookieManager::set($token);
        $helper->log('VOUCHER SESSION REAUTHORIZED', [
            'mac' => $mac,
            'previous_mac' => strtolower((string)$existingSession['mac']),
            'remaining_minutes' => $remaining,
            'apMac' => $apMac,
        ]);
        header('Location: success.php?url=' . urlencode($url));
        exit;
    }

    $reserved = $db->reserveVoucher(
        $siteKey,
        $voucherCode,
        $mac,
        $quota,
        $apMac,
        $voucherId ?: null,
        (string)($voucher['note'] ?? ''),
        $minutes,
        $uploadKbps > 0 ? $uploadKbps : null,
        $downloadKbps > 0 ? $downloadKbps : null,
        $dataLimitMb > 0 ? $dataLimitMb : null,
        $voucherCreatedAt > 0 ? $voucherCreatedAt : null
    );
    if (!$reserved) {
        $helper->log('VOUCHER AUTH REJECTED', [
            'reason' => 'quota_exhausted_or_device_already_used',
            'mac' => $mac,
            'apMac' => $apMac,
        ]);
        header('Location: index.php?site=' . urlencode($siteKey) . '&id=' . urlencode($mac) . '&ap=' . urlencode($apMac ?? '') . '&url=' . urlencode($url) . '&error=used');
        exit;
    }

    try {
        if (!$helper->authorize($mac, $minutes, $apMac)) {
            throw new Exception('Authorization failed');
        }
    } catch (Throwable $e) {
        $db->releaseVoucherReservation($siteKey, $voucherCode, $mac);
        throw $e;
    }

    // Cookie anti-rotation
    $token = CookieManager::generateToken();
    CookieManager::set($token);
    $db->saveSession($siteKey, $mac, $code, $minutes, $token);
    $helper->log('VOUCHER AUTHORIZED', [
        'mac' => $mac,
        'apMac' => $apMac,
        'duration_minutes' => $minutes,
        'quota' => $quota,
    ]);

    $usedCount = (int)($voucher['used'] ?? 0);
    try {
        $redemptions = $db->getRedemptionsForCode($siteKey, $voucherCode);
        $localUsed = count(array_unique(array_column($redemptions, 'mac')));
        $usedCount = max($usedCount, $localUsed);
    } catch (Throwable $e) {
        $helper->log('VOUCHER QUOTA CHECK FAILED', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'mac' => $mac,
        ]);
    }
    if ($quota > 0 && $usedCount >= $quota) {
        if ($voucherId === '') {
            $helper->log('VOUCHER REVOKE SKIPPED', [
                'reason' => 'missing_voucher_id',
                'mac' => $mac,
                'used' => $usedCount,
                'quota' => $quota,
            ]);
        } else {
            try {
                $db->markVoucherRevocationPending($siteKey, $voucherCode);
                if ($helper->revokeVoucher($voucherId)) {
                    try {
                        $db->markVoucherRevoked($siteKey, $voucherCode);
                    } catch (Throwable $e) {
                        $helper->log('VOUCHER REVOKED BUT LOCAL STATUS SAVE FAILED', [
                            'error' => $e->getMessage(),
                            'mac' => $mac,
                        ]);
                    }
                    $helper->log('VOUCHER REVOKED AFTER QUOTA REACHED', [
                        'mac' => $mac,
                        'used' => $usedCount,
                        'quota' => $quota,
                    ]);
                } else {
                    $db->markVoucherRevocationFailed($siteKey, $voucherCode, 'UniFi returned false');
                    $helper->log('VOUCHER REVOKE FAILED', [
                        'reason' => 'unifi_returned_false',
                        'mac' => $mac,
                        'used' => $usedCount,
                        'quota' => $quota,
                    ]);
                }
            } catch (Throwable $e) {
                try {
                    $db->markVoucherRevocationFailed($siteKey, $voucherCode, $e->getMessage());
                } catch (Throwable $dbError) {
                    $helper->log('VOUCHER REVOKE FAILURE STATUS SAVE FAILED', [
                        'error' => $dbError->getMessage(),
                        'mac' => $mac,
                    ]);
                }
                $helper->log('VOUCHER REVOKE FAILED', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'mac' => $mac,
                    'used' => $usedCount,
                    'quota' => $quota,
                ]);
            }
        }
    }

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