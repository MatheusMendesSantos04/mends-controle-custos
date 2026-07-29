<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($titulo_pagina) ? e($titulo_pagina) . ' — ' : '' ?>Minhas Finanças</title>
<link rel="stylesheet" href="/css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
</head>
<body>
<?php $logado = !empty($_SESSION['usuario_id']); $pagina = basename($_SERVER['PHP_SELF']); ?>
<?php if ($logado): ?>
<div class="app-shell">
    <aside class="sidebar">
        <a href="/index.php" class="marca"><span class="bola">M</span> Minhas Finanças</a>
        <nav>
            <a href="/index.php" class="nav-item <?= $pagina === 'index.php' ? 'ativo' : '' ?>">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                <span class="rotulo-nav">Painel</span>
            </a>
            <a href="/transacoes.php" class="nav-item <?= in_array($pagina, ['transacoes.php', 'transacao_form.php']) ? 'ativo' : '' ?>">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h.01"/><path d="M3 18h.01"/><path d="M3 6h.01"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M8 6h13"/></svg>
                <span class="rotulo-nav">Lançamentos</span>
            </a>
            <a href="/categorias.php" class="nav-item <?= $pagina === 'categorias.php' ? 'ativo' : '' ?>">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42Z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                <span class="rotulo-nav">Categorias</span>
            </a>
            <a href="/relatorios.php" class="nav-item <?= $pagina === 'relatorios.php' ? 'ativo' : '' ?>">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                <span class="rotulo-nav">Relatórios</span>
            </a>
        </nav>
        <a href="/auth/logout.php" class="nav-item nav-sair">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="rotulo-nav">Sair</span>
        </a>
    </aside>
    <main class="main-content">
<?php else: ?>
<div class="auth-shell">
<?php endif; ?>
