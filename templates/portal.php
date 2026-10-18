<!DOCTYPE html>
<html lang="<?= htmlspecialchars($site['lang'] ?? 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wi-Fi Guest – <?= htmlspecialchars($site['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1><?= htmlspecialchars($site['name']) ?></h1>
        <h2 class="subtitle"><?= htmlspecialchars($site['welcome']) ?> (<?= htmlspecialchars($site['country']) ?>)</h2>

        <?php if ($error === 'badcode'): ?>
            <div class="alert error">Code voucher invalide ou déjà utilisé.</div>
        <?php elseif ($error === 'server'): ?>
            <div class="alert error">Erreur serveur. Réessaie dans un instant.</div>
        <?php elseif ($error === 'invalid'): ?>
            <div class="alert error">Données manquantes.</div>
        <?php endif; ?>

        <!-- Packages / Prix (dynamique par site) -->
        <div class="packages">
            <h3>Nos forfaits</h3>
            <ul>
                <?php foreach ($site['packages'] as $pkg): ?>
                    <li>
                        <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                        – <?= number_format($pkg['price'], 2) ?> <?= htmlspecialchars($site['currency']) ?>
                        <span><?= htmlspecialchars($pkg['desc']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            
        </div>

        <!-- Formulaire style UniFi natif -->
        <form method="post" action="auth.php" class="voucher-form">
            <input type="hidden" name="mac" value="<?= htmlspecialchars($mac) ?>">
            <input type="hidden" name="ap" value="<?= htmlspecialchars($apMac ?? '') ?>">
            <input type="hidden" name="url" value="<?= htmlspecialchars($url) ?>">
            <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">

            <label for="voucher">Code d’accès (voucher)</label>
            <input type="text"
                   id="voucher"
                   name="voucher"
                   placeholder="XXXX-XXXX-XXXX"
                   autocomplete="off"
                   autocapitalize="characters"
                   required
                   autofocus>

            <button type="submit">Se connecter</button>
        </form>

        <h3 class="footer-note">
            <?= htmlspecialchars($site['supportText'] ?? 'Support technique') ?>
        </h3>
    </div>
</div>
</body>
</html>