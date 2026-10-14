<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_GET['site'] ?? $_POST['site'] ?? array_key_first($config['sites']);
$site    = $config['sites'][$siteKey];
$error   = null;
$created = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $helper = new UniFiHelper($config, $siteKey);
        $created = $helper->createVoucher(
            (int)$_POST['minutes'],
            max(1, (int)$_POST['count']),
            (int)$_POST['quota'],
            trim($_POST['note'] ?? ''),
            !empty($_POST['up']) ? (int)$_POST['up'] : null,
            !empty($_POST['down']) ? (int)$_POST['down'] : null,
            !empty($_POST['megabytes']) ? (int)$_POST['megabytes'] : null
        );
        if (empty($created)) {
            $error = 'Création OK mais codes non récupérés (réessaie ou regarde la liste).';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer vouchers</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Créer des vouchers</h1>
        <p class="subtitle"><?= htmlspecialchars($site['name']) ?></p>

        <p><a href="index.php?site=<?= urlencode($siteKey) ?>">← Retour liste</a></p>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($created)): ?>
            <div class="alert" style="background:#14532d;color:#86efac;">
                <strong><?= count($created) ?> voucher(s) créé(s) :</strong>
                <ul style="margin-top:10px;">
                <?php foreach ($created as $v):
                    $c = $v['code'] ?? '';
                    if (strlen($c) === 10) $c = substr($c,0,5).'-'.substr($c,5);
                ?>
                    <li><code style="font-size:1.1rem;"><?= htmlspecialchars($c) ?></code></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">

            <label>Durée (minutes)</label>
            <input type="number" name="minutes" value="1440" min="1" required>
            <!-- Raccourcis packages du site -->
            <p style="font-size:0.8rem;color:#94a3b8;margin:-10px 0 14px;">
                Raccourcis :
                <?php foreach ($site['packages'] as $pkg): ?>
                    <a href="#" onclick="document.querySelector('[name=minutes]').value=<?= (int)$pkg['minutes'] ?>;return false;">
                        <?= htmlspecialchars($pkg['name']) ?> (<?= (int)$pkg['minutes'] ?> min)
                    </a>
                <?php endforeach; ?>
            </p>

            <label>Nombre de vouchers</label>
            <input type="number" name="count" value="1" min="1" max="50" required>

            <label>Quota (1 = single-use, 0 = multi illimité)</label>
            <input type="number" name="quota" value="1" min="0" required>

            <label>Note (optionnel)</label>
            <input type="text" name="note" placeholder="ex: vente comptoir 09/10">

            <label>Limite upload Kbps (optionnel)</label>
            <input type="number" name="up" placeholder="vide = illimité">

            <label>Limite download Kbps (optionnel)</label>
            <input type="number" name="down" placeholder="vide = illimité">

            <label>Quota data Mo (optionnel)</label>
            <input type="number" name="megabytes" placeholder="vide = illimité">

            <button type="submit">Créer</button>
        </form>
    </div>
</div>
</body>
</html>