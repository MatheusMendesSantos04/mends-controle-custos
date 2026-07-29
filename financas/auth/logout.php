<?php
require __DIR__ . '/../config.php';
$_SESSION = [];
session_destroy();
header('Location: /auth/login.php');
exit;
