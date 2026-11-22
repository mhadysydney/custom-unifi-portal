<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\Database;
use Portal\UniFiHelper;

$config = require __DIR__ . '/../../config/sites.php';
$siteKey = $_GET['site'] ?? array_key_first($config['sites']);
$code = preg_replace('/\D/', '', $_GET['code'] ?? '');

if (!isset($config['sites'][$siteKey]) || $code === '') {
    header('Location: index.php');
    exit;
}

$db = new Database();
$helper = new UniFiHelper($config, $siteKey);
$redemptions = $db->getRedemptionsForCode($siteKey, $code);
$vouchers = $helper->listVouchers();
$voucher = null;
foreach ($vouchers as $row) {
    if (preg_replace('/\D/', '', (string)($row['code'] ?? '')) === $code) {
        $voucher = $row;
        break;
    }
}
if ($voucher === null && $redemptions) {
    $latestRedemption = $redemptions[0];
    $voucher = [
        'duration' => $latestRedemption['duration_min'] ?? 0,
        'quota' => $latestRedemption['quota'] ?? 1,
        'note' => $latestRedemption['note'] ?? '',
        'qos_rate_max_up' => $latestRedemption['qos_rate_max_up'] ?? null,
        'qos_rate_max_down' => $latestRedemption['qos_rate_max_down'] ?? null,
        'data_limit_mb' => $latestRedemption['data_limit_mb'] ?? null,
    ];
}

function bytesLabel($bytes): string
{
    $bytes = (float)$bytes;
    if ($bytes >= 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    if ($bytes >= 1024 * 1024) return number_format($bytes / (1024 * 1024), 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return number_format($bytes) . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Détails du voucher <?= htmlspecialchars($code) ?></title>
  <link rel="stylesheet" href="../assets/admin.css">
  <style>
    body { max-width: 1000px; margin: 24px auto; padding: 0 16px; background: #0f172a; color: #e2e8f0; font-family: system-ui, sans-serif; }
    .card { background: #1e293b; border-radius: 14px; padding: 20px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 10px; border-bottom: 1px solid #334155; }
    th { color: #94a3b8; }
    a { color: #38bdf8; }
    .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .summary div { background: #0f172a; border-radius: 10px; padding: 14px; }
    .summary strong { display: block; color: #38bdf8; font-size: 1.2rem; }
  </style>
</head>
<body>
  <p><a href="index.php?site=<?= urlencode($siteKey) ?>">← Retour aux vouchers</a></p>
  <div class="card">
    <h1>Voucher <?= htmlspecialchars(substr($code, 0, 5) . '-' . substr($code, 5)) ?></h1>
    <div class="summary">
      <div><strong><?= (int)($voucher['duration'] ?? 0) ?> min</strong>Durée</div>
      <div><strong><?= (int)($voucher['quota'] ?? 0) ?></strong>Quota</div>
      <div><strong><?= count(array_unique(array_column($redemptions, 'mac'))) ?></strong>Appareils</div>
      <div><strong><?= $redemptions ? date('d/m/Y H:i', (int)max(array_column($redemptions, 'expires_at'))) : '—' ?></strong>Expiration max.</div>
    </div>
  </div>
  <div class="card">
    <h2>Utilisations</h2>
    <?php if (!$redemptions): ?>
      <p>Aucune utilisation enregistrée.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>MAC</th><th>Début</th><th>Expiration</th><th>Upload</th><th>Download</th></tr></thead>
        <tbody>
        <?php foreach ($redemptions as $redemption):
          $guest = $helper->getGuestStats((string)$redemption['mac']);
          $txBytes = (int)($guest['tx_bytes'] ?? $guest['bytes'] ?? 0);
          $rxBytes = (int)($guest['rx_bytes'] ?? 0);
        ?>
          <tr>
            <td><code><?= htmlspecialchars(strtolower($redemption['mac'])) ?></code></td>
            <td><?= date('d/m/Y H:i', (int)$redemption['authorized_at']) ?></td>
            <td><?= $redemption['expires_at'] ? date('d/m/Y H:i', (int)$redemption['expires_at']) : '—' ?></td>
            <td><?= bytesLabel($txBytes) ?></td>
            <td><?= bytesLabel($rxBytes) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
