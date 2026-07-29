<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($titulo_pagina) ? e($titulo_pagina) . ' — ' : '' ?>Minhas Finanças</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>

<div class="topbar">
    <a href="/index.php" class="marca">💰 Minhas Finanças</a>
    <?php if (!empty($_SESSION['usuario_id'])): ?>
    <nav>
        <a href="/index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'ativo' : '' ?>">Painel</a>
        <a href="/transacoes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'transacoes.php' ? 'ativo' : '' ?>">Lançamentos</a>
        <a href="/categorias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'ativo' : '' ?>">Categorias</a>
        <a href="/relatorios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'relatorios.php' ? 'ativo' : '' ?>">Relatórios</a>
        <a href="/auth/logout.php">Sair</a>
    </nav>
    <?php endif; ?>
</div>

<div class="wrapper">
