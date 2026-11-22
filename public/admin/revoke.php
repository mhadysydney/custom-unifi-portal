<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_POST['site'] ?? '';
$id      = $_POST['id'] ?? '';
$code    = preg_replace('/\D/', '', $_POST['code'] ?? '');

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Requête expirée. Actualisez la page.');
}

if ($siteKey && $id && $code !== '' && isset($config['sites'][$siteKey])) {
    try {
        $helper = new UniFiHelper($config, $siteKey);
        $db = new \Portal\Database();
        $voucherExists = false;
        foreach ($helper->listVouchers() as $candidate) {
            if (($candidate['_id'] ?? null) === $id) {
                $voucherExists = true;
                break;
            }
        }

        if (!$voucherExists) {
            $db->markVoucherRevoked($siteKey, $code);
            header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revoked');
            exit;
        }

        $db->markVoucherRevocationPending($siteKey, $code);
        if ($helper->revokeVoucher($id)) {
            $db->markVoucherRevoked($siteKey, $code);
            header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revoked');
            exit;
        }
        $db->markVoucherRevocationFailed($siteKey, $code, 'UniFi returned false');
        header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revocation_failed');
        exit;
    } catch (Exception $e) {
        if (isset($db)) {
            try {
                $db->markVoucherRevocationFailed($siteKey, $code, $e->getMessage());
            } catch (Throwable $dbError) {
                error_log('Unable to persist voucher revocation failure: ' . $dbError->getMessage());
            }
        }
        if (isset($helper)) {
            $helper->log('REVOKE VOUCHER ERROR', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'site'  => $siteKey,
            'id'    => $id,
            ]);
        }
        header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revocation_failed');
        exit;
    }
}

header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revocation_failed');
exit;