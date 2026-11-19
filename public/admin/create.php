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
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Requête expirée. Actualisez la page.');
    }
    $minutes = filter_input(INPUT_POST, 'minutes', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 525600]]);
    $count = filter_input(INPUT_POST, 'count', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 250]]);
    $quota = filter_input(INPUT_POST, 'quota', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    if ($minutes === false || $count === false || $quota === false) {
        $error = 'Paramètres invalides.';
    } else {
    try {
        
        $created = $helper->createVoucher(
            $minutes,
            $count,
            $quota,
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
      <button type="button" class="btn no-print" onclick="window.print()" style="margin-top:10px">Imprimer / PDF</button>
      <div class="voucher-print-grid">
        <?php foreach ($created as $v):
          $c = $v['code'] ?? '';
          if (strlen($c) === 10) $c = substr($c,0,5).'-'.substr($c,5);
        ?>
          <div class="voucher-print-card">
            <div>Wi-Fi voucher</div>
            <strong><?= htmlspecialchars($c) ?></strong>
            <div>Validité : <?= (int)($_POST['minutes'] ?? 0) ?> min</div>
          </div>

          <style>
          .voucher-print-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 12px; margin-top: 14px; }
          .voucher-print-card { border: 2px dashed #334155; border-radius: 8px; background: #fff; color: #111827; padding: 18px 8px; text-align: center; break-inside: avoid; }
          .voucher-print-card strong { display: block; font-size: 1.25rem; margin: 12px 0; }
          @media print {
            body { background: #fff !important; color: #111 !important; }
            .no-print, .admin-card > h1, .admin-card > .subtitle, .admin-card > p, #createForm { display: none !important; }
            .admin-card { box-shadow: none; background: #fff; }
            .alert-ok { background: #fff; color: #111; }
            .voucher-print-grid { grid-template-columns: repeat(4, 1fr); }
            .voucher-print-card { break-inside: avoid; }
          }
          </style>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="form-grid" id="createForm">
    <input type="hidden" name="site" value="<?= htmlspecialchars($siteKey) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

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