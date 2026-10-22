<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_GET['site'] ?? array_key_first($config['sites']);

if (!isset($config['sites'][$siteKey])) {
    $siteKey = array_key_first($config['sites']);
}

$from   = !empty($_GET['from']) ? strtotime($_GET['from'] . ' 00:00:00') : null;
$to     = !empty($_GET['to'])   ? strtotime($_GET['to']   . ' 23:59:59') : null;
$filter = $_GET['filter'] ?? 'all'; // all | used | remaining
$msg    = $_GET['msg'] ?? null;

$error = null;
$stats = [
    'all' => [], 'used' => [], 'remaining' => [],
    'count_all' => 0, 'count_used' => 0, 'count_remaining' => 0,
];
$list = [];

try {
    $helper = new UniFiHelper($config, $siteKey);
    $stats  = $helper->getVoucherStats($from, $to);

    $list = match ($filter) {
        'used'      => $stats['used'],
        'remaining' => $stats['remaining'],
        default     => $stats['all'],
    };

    // Filtre date sur "all" (used/remaining déjà filtrés dans getVoucherStats)
    if ($filter === 'all' && ($from || $to)) {
        $list = array_values(array_filter($stats['all'], function ($v) use ($from, $to) {
            $c = (int)($v['create_time'] ?? 0);
            if ($from && $c < $from) return false;
            if ($to && $c > $to) return false;
            return true;
        }));
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

/** Format code UniFi 10 chiffres → XXXXX-XXXXX */
function formatVoucherCode(string $code): string
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) === 10) {
        return substr($code, 0, 5) . '-' . substr($code, 5);
    }
    return $code;
}

/** Limite up/down (champs variables selon version UniFi) */
function voucherRate(array $v, string $dir): string
{
    if ($dir === 'up') {
        $val = $v['qos_rate_max_up'] ?? $v['up'] ?? $v['rate_max_up'] ?? null;
    } else {
        $val = $v['qos_rate_max_down'] ?? $v['down'] ?? $v['rate_max_down'] ?? null;
    }
    if ($val === null || $val === '' || (int)$val <= 0) {
        return '—';
    }
    return (int)$val . ' Kbps';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Vouchers</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            padding: 24px 16px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .card {
            background: #1e293b;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,.35);
        }
        h1 { margin: 0 0 16px; font-size: 1.4rem; }

        .admin-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .admin-nav a {
            padding: 10px 14px;
            border-radius: 8px;
            background: #334155;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .admin-nav a.active,
        .admin-nav a:hover { background: #38bdf8; color: #0f172a; }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .alert-ok { background: #14532d; color: #86efac; }
        .alert-error { background: #7f1d1d; color: #fecaca; }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #0f172a;
            padding: 14px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box strong {
            display: block;
            font-size: 1.4rem;
            color: #38bdf8;
        }

        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            align-items: end;
        }
        .filters label {
            display: block;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .filters input,
        .filters select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #e2e8f0;
        }
        .filters button {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            background: #38bdf8;
            color: #0f172a;
            font-weight: 600;
            cursor: pointer;
        }

        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 720px;
        }
        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #334155;
            white-space: nowrap;
        }
        th { color: #94a3b8; font-weight: 500; }

        .badge {
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        .badge.ok { background: #14532d; color: #86efac; }
        .badge.used { background: #7f1d1d; color: #fecaca; }

        .btn-danger {
            background: #b91c1c;
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .btn-danger:hover { background: #dc2626; }

        @media (max-width: 600px) {
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Admin Vouchers</h1>

        <div class="admin-nav">
            <?php foreach ($config['sites'] as $key => $s): ?>
                <a href="?site=<?= urlencode($key) ?>"
                   class="<?= $key === $siteKey ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['name']) ?>
                </a>
            <?php endforeach; ?>
            <a href="create.php?site=<?= urlencode($siteKey) ?>">+ Créer</a>
            <a href="logout.php">Déconnexion</a>
        </div>

        <?php if ($msg === 'created'): ?>
            <div class="alert alert-ok">Voucher(s) créé(s).</div>
        <?php elseif ($msg === 'revoked'): ?>
            <div class="alert alert-ok">Voucher révoqué.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                Erreur API : <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-box">
                <strong><?= (int)$stats['count_all'] ?></strong> Total
            </div>
            <div class="stat-box">
                <strong><?= (int)$stats['count_remaining'] ?></strong> Restants
            </div>
            <div class="stat-box">
                <strong><?= (int)$stats['count_used'] ?></strong> Utilisés
            </div>
        </div>

        <form method="get" class="filters">
            <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">
            <div>
                <label>Du</label>
                <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
            </div>
            <div>
                <label>Au</label>
                <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
            </div>
            <div>
                <label>Filtre</label>
                <select name="filter">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tous</option>
                    <option value="remaining" <?= $filter === 'remaining' ? 'selected' : '' ?>>Restants</option>
                    <option value="used" <?= $filter === 'used' ? 'selected' : '' ?>>Utilisés</option>
                </select>
            </div>
            <button type="submit">Filtrer</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Durée</th>
                        <th>Quota</th>
                        <th>Utilisé</th>
                        <th>Up</th>
                        <th>Down</th>
                        <th>Créé</th>
                        <th>Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="9"><?= $error ? '—' : 'Aucun voucher' ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($list as $v):
                        $v = (array)$v;
                        $quota  = (int)($v['quota'] ?? 0);
                        $used   = (int)($v['used'] ?? 0);
                        $isUsed = ($quota > 0 && $used >= $quota);
                        $code   = formatVoucherCode((string)($v['code'] ?? ''));
                    ?>
                    <tr>
                        <td><code><?= htmlspecialchars($code) ?></code></td>
                        <td><?= (int)($v['duration'] ?? 0) ?> min</td>
                        <td><?= $quota === 0 ? '∞' : $quota ?></td>
                        <td>
                            <span class="badge <?= $isUsed ? 'used' : 'ok' ?>">
                                <?= $used ?><?= $quota > 0 ? '/' . $quota : '' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(voucherRate($v, 'up')) ?></td>
                        <td><?= htmlspecialchars(voucherRate($v, 'down')) ?></td>
                        <td>
                            <?= !empty($v['create_time'])
                                ? date('d/m/Y H:i', (int)$v['create_time'])
                                : '—' ?>
                        </td>
                        <td><?= htmlspecialchars((string)($v['note'] ?? '')) ?></td>
                        <td>
                            <?php if (!$isUsed && !empty($v['_id'])): ?>
                            <form method="post" action="revoke.php" style="display:inline"
                                  onsubmit="return confirm('Révoquer ce voucher ?');">
                                <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($v['_id']) ?>">
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
</div>
</body>
</html>