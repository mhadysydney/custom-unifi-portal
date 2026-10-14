<?php
session_start();
$secrets = require __DIR__ . '/../../config/secrets.php';

if (empty($_SESSION['admin_ok'])) {
    header('Location: login.php');
    exit;
}

?>