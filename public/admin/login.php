<?php
session_start();
$secrets = require __DIR__ . '/../../config/secrets.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['user'] ?? '';
    $p = $_POST['pass'] ?? '';
    if ($u === ($secrets['admin_user'] ?? '') && $p === ($secrets['admin_pass'] ?? '')) {
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
    <title>Admin Portal</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Admin Vouchers</h1>
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Utilisateur</label>
            <input type="text" name="user" required autofocus>
            <label>Mot de passe</label>
            <input type="password" name="pass" required>
            <button type="submit">Connexion</button>
        </form>
    </div>
</div>
</body>
</html>