<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/_auth.php';

use Portal\UniFiHelper;

$config  = require __DIR__ . '/../../config/sites.php';
$siteKey = $_GET['site'] ?? $_POST['site'] ?? array_key_first($config['sites']);
$helper = new UniFiHelper($config, $siteKey);
$site    = $config['sites'][$siteKey];
$error   = null;
$created = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
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
        $helper->log('CREATE VOUCHER ERROR', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'site'  => $siteKey,
            'minutes' => (int)$_POST['minutes'],
            'count'   => max(1, (int)$_POST['count']),
            'quota'   => (int)$_POST['quota'],
            'note'    => trim($_POST['note'] ?? ''),
            'up'      => !empty($_POST['up']) ? (int)$_POST['up'] : null,
            'down'    => !empty($_POST['down']) ? (int)$_POST['down'] : null,
            'megabytes' => !empty($_POST['megabytes']) ? (int)$_POST['megabytes'] : null,
        ]);
        $error = 'Erreur lors de la création du voucher : ' . $e->getMessage();
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
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<div class="container">
<div class="admin-card">
  <h1>Créer des vouchers</h1>
  <p class="subtitle"><?= htmlspecialchars($site['name']) ?></p>
  <p><a href="index.php?site=<?= urlencode($siteKey) ?>">← Retour liste</a></p>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($created)): ?>
    <div class="alert alert-ok">
      <strong><?= count($created) ?> voucher(s) créé(s)</strong>
      <ul style="margin:10px 0 0;padding-left:18px">
        <?php foreach ($created as $v):
          $c = $v['code'] ?? '';
          if (strlen($c) === 10) $c = substr($c,0,5).'-'.substr($c,5);
        ?>
          <li><code><?= htmlspecialchars($c) ?></code></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="form-grid" id="createForm">
    <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">

    <div class="form-row">
      <label for="minutes">Durée (minutes)</label>
      <input type="number" id="minutes" name="minutes" value="1440" min="1" required>
      <div class="form-hint">
        <?php foreach ($site['packages'] as $pkg): ?>
          <a href="#" onclick="document.getElementById('minutes').value=<?= (int)$pkg['minutes'] ?>;return false;">
            <?= htmlspecialchars($pkg['name']) ?> (<?= (int)$pkg['minutes'] ?> min)
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-row">
      <label for="count">Nombre de vouchers</label>
      <input type="number" id="count" name="count" value="1" min="1" max="50" required>
    </div>

    <div class="form-row">
      <label for="quota">Quota (1 = single-use, 0 = multi illimité)</label>
      <input type="number" id="quota" name="quota" value="1" min="0" required>
    </div>

    <div class="form-row">
      <label for="note">Note (optionnel)</label>
      <input type="text" id="note" name="note" placeholder="ex: vente comptoir 09/10">
    </div>

    <div class="form-row">
      <label for="up">Limite upload Kbps (optionnel)</label>
      <input type="number" id="up" name="up" placeholder="vide = illimité" min="0">
    </div>

    <div class="form-row">
      <label for="down">Limite download Kbps (optionnel)</label>
      <input type="number" id="down" name="down" placeholder="vide = illimité" min="0">
    </div>

    <div class="form-row">
      <label for="megabytes">Quota data Mo (optionnel)</label>
      <input type="number" id="megabytes" name="megabytes" placeholder="vide = illimité" min="0">
    </div>

    <div class="form-actions">
      <button type="submit" class="btn" id="createBtn">
        <span class="spinner"></span>
        <span class="btn-text">Créer</span>
      </button>
    </div>
  </form>
</div>


</div>
</body>
<script>
document.getElementById('createForm').addEventListener('submit', function () {
  const btn = document.getElementById('createBtn');
  btn.disabled = true;
  btn.classList.add('is-loading');
});
</script>
</html>