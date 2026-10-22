<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_POST['site'] ?? '';
$id      = $_POST['id'] ?? '';

if ($siteKey && $id && isset($config['sites'][$siteKey])) {
    try {
        $helper = new UniFiHelper($config, $siteKey);
        $helper->revokeVoucher($id);
    } catch (Exception $e) {
        $helper->log('REVOKE VOUCHER ERROR', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'site'  => $siteKey,
            'id'    => $id,
        ]);
    }
}

header('Location: index.php?site=' . urlencode($siteKey) . '&msg=revoked');
exit;