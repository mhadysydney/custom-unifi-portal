<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$secrets = require __DIR__ . '/../../config/secrets.php';
$error = null;
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['login_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Requête expirée.');
    }
    $u = $_POST['user'] ?? '';
    $p = $_POST['pass'] ?? '';
    if ($u === ($secrets['admin_user'] ?? '') && $p === ($secrets['admin_pass'] ?? '')) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Identifiants incorrects';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin – Connexion</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="admin-body">
  <div class="login-wrap">
    <div class="admin-card login-card">
      <h1>Admin Vouchers</h1>
      <p class="subtitle">Connexion</p>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="form-grid" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['login_csrf']) ?>">
        <div class="form-row">
          <label for="user">Utilisateur</label>
          <input type="text" id="user" name="user" required autofocus autocomplete="username">
        </div>
        <div class="form-row">
          <label for="pass">Mot de passe</label>
          <input type="password" id="pass" name="pass" required autocomplete="current-password">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn" id="loginBtn">
            <span class="spinner"></span>
            <span class="btn-text">Connexion</span>
          </button>
        </div>
      </form>
    </div>
  </div>
  
</body>
<script>
    document.getElementById('loginForm').addEventListener('submit', function () {
      const btn = document.getElementById('loginBtn');
      btn.disabled = true;
      btn.classList.add('is-loading');
    });
  </script>
</html>

