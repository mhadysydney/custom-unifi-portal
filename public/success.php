<?php
$url = 'https://www.google.com';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecté</title>
    <link rel="stylesheet" href="/assets/style.css">
    <meta http-equiv="refresh" content="2;url=<?= htmlspecialchars($url) ?>">
</head>
<body>
<div class="container">
    <div class="card success">
        <h1>Vous êtes connecté !</h1>
        <p>Redirection en cours…</p>
        <a href="<?= htmlspecialchars($url) ?>">Continuer</a>
    </div>
</div>
</body>
</html>