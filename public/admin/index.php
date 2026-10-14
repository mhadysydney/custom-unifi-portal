<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_GET['site'] ?? array_key_first($config['sites']);
$from    = !empty($_GET['from']) ? strtotime($_GET['from'] . ' 00:00:00') : null;
$to      = !empty($_GET['to'])   ? strtotime($_GET['to']   . ' 23:59:59') : null;
$filter  = $_GET['filter'] ?? 'all'; // all | used | remaining

$helper = new UniFiHelper($config, $siteKey);
$stats  = $helper->getVoucherStats($from, $to);

$list = match ($filter) {
    'used'      => $stats['used'],
    'remaining' => $stats['remaining'],
    default     => $stats['all'],
};

// Si période active, on a déjà filtré dans getVoucherStats pour used/remaining ;
// pour "all" on refiltre par date
if ($filter === 'all' && ($from || $to)) {
    $list = array_filter($stats['all'], function ($v) use ($from, $to) {
        $c = (int)($v['create_time'] ?? 0);
        if ($from && $c < $from) return false;
        if ($to && $c > $to) return false;
        return true;
    });
}

$msg = $_GET['msg'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Vouchers</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .admin-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .admin-nav a, .admin-nav button {
            padding: 10px 14px; border-radius: 8px; background: #334155; color: #e2e8f0;
            text-decoration: none; border: none; cursor: pointer; font-size: 0.9rem;
        }
        .admin-nav a.active, .admin-nav a:hover { background: #38bdf8; color: #0f172a; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-box { background: #0f172a; padding: 14px; border-radius: 10px; text-align: center; }
        .stat-box strong { display: block; font-size: 1.4rem; color: #38bdf8; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; }
        .badge { padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; }
        .badge.ok { background: #14532d; color: #86efac; }
        .badge.used { background: #7f1d1d; color: #fecaca; }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; align-items: end; }
        .filters input, .filters select { padding: 8px; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #e2e8f0; }
        .btn-danger { background: #b91c1c !important; color: #fff !important; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.8rem; }
        .container { max-width: 900px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Admin Vouchers</h1>

        <div class="admin-nav">
            <?php foreach ($config['sites'] as $key => $s): ?>
                <a href="?site=<?= urlencode($key) ?>" class="<?= $key === $siteKey ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['name']) ?>
                </a>
            <?php endforeach; ?>
            <a href="create.php?site=<?= urlencode($siteKey) ?>">+ Créer</a>
            <a href="logout.php">Déconnexion</a>
        </div>

        <?php if ($msg === 'created'): ?>
            <div class="alert" style="background:#14532d;color:#86efac;">Voucher(s) créé(s).</div>
        <?php elseif ($msg === 'revoked'): ?>
            <div class="alert" style="background:#14532d;color:#86efac;">Voucher révoqué.</div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-box"><strong><?= (int)$stats['count_all'] ?></strong> Total</div>
            <div class="stat-box"><strong><?= (int)$stats['count_remaining'] ?></strong> Restants</div>
            <div class="stat-box"><strong><?= (int)$stats['count_used'] ?></strong> Utilisés</div>
        </div>

        <form method="get" class="filters">
            <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">
            <div>
                <label>Du</label><br>
                <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
            </div>
            <div>
                <label>Au</label><br>
                <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
            </div>
            <div>
                <label>Filtre</label><br>
                <select name="filter">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="remaining" <?= $filter === 'remaining' ? 'selected' : '' ?>>Restants</option>
                    <option value="used" <?= $filter === 'used' ? 'selected' : '' ?>>Utilisés</option>
                </select>
            </div>
            <button type="submit">Filtrer</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Durée</th>
                    <th>Quota</th>
                    <th>Utilisé</th>
                    <th>Créé</th>
                    <th>Note</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($list)): ?>
                <tr><td colspan="7">Aucun voucher</td></tr>
            <?php else: ?>
                <?php foreach ($list as $v):
                    $quota = (int)($v['quota'] ?? 0);
                    $used  = (int)($v['used'] ?? 0);
                    $isUsed = ($quota > 0 && $used >= $quota);
                    $code  = $v['code'] ?? '';
                    // Format code type UniFi : 12345-67890
                    if (strlen($code) === 10) {
                        $code = substr($code, 0, 5) . '-' . substr($code, 5);
                    }
                ?>
                <tr>
                    <td><code><?= htmlspecialchars($code) ?></code></td>
                    <td><?= (int)($v['duration'] ?? 0) ?> min</td>
                    <td><?= $quota === 0 ? '∞' : $quota ?></td>
                    <td>
                        <span class="badge <?= $isUsed ? 'used' : 'ok' ?>">
                            <?= $used ?><?= $quota > 0 ? '/'.$quota : '' ?>
                        </span>
                    </td>
                    <td><?= !empty($v['create_time']) ? date('d/m/Y H:i', $v['create_time']) : '-' ?></td>
                    <td><?= htmlspecialchars($v['note'] ?? '') ?></td>
                    <td>
                        <?php if (!$isUsed): ?>
                        <form method="post" action="revoke.php" style="display:inline"
                              onsubmit="return confirm('Révoquer ce voucher ?');">
                            <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($v['_id'] ?? '') ?>">
                            <button type="submit" class="btn-danger">Révoquer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>