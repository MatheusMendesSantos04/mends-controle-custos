<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$familia_id = $_SESSION['familia_id'];
$mes_atual  = date('Y-m');

// Totais do mês atual
$stmt = $pdo->prepare("
    SELECT tipo, COALESCE(SUM(valor),0) AS total
    FROM transacoes
    WHERE familia_id = ? AND DATE_FORMAT(data_transacao, '%Y-%m') = ?
    GROUP BY tipo
");
$stmt->execute([$familia_id, $mes_atual]);
$totais = ['receita' => 0, 'despesa' => 0];
foreach ($stmt->fetchAll() as $linha) {
    $totais[$linha['tipo']] = (float) $linha['total'];
}
$saldo = $totais['receita'] - $totais['despesa'];

// Buscar código de convite da família (para convidar outros membros)
$stmt = $pdo->prepare('SELECT nome, codigo_convite FROM familias WHERE id = ?');
$stmt->execute([$familia_id]);
$familia = $stmt->fetch();

// Últimos 8 lançamentos
$stmt = $pdo->prepare("
    SELECT t.*, c.nome AS categoria_nome, c.cor AS categoria_cor, u.nome AS usuario_nome
    FROM transacoes t
    JOIN categorias c ON c.id = t.categoria_id
    JOIN usuarios u ON u.id = t.usuario_id
    WHERE t.familia_id = ?
    ORDER BY t.data_transacao DESC, t.id DESC
    LIMIT 8
");
$stmt->execute([$familia_id]);
$ultimos = $stmt->fetchAll();

$titulo_pagina = 'Painel';
require __DIR__ . '/includes/header.php';
?>

<h1>Olá, <?= e($_SESSION['usuario_nome']) ?> 👋</h1>
<p style="color:#5B6B5F; margin-top:-0.6rem;">
    Família <strong><?= e($familia['nome']) ?></strong> —
    código de convite para outros membros: <strong><?= e($familia['codigo_convite']) ?></strong>
</p>

<div class="grade-resumo">
    <div class="resumo-item receita">
        <span class="rotulo">Receitas do mês</span>
        <span class="numero"><?= formatar_moeda($totais['receita']) ?></span>
    </div>
    <div class="resumo-item despesa">
        <span class="rotulo">Despesas do mês</span>
        <span class="numero"><?= formatar_moeda($totais['despesa']) ?></span>
    </div>
    <div class="resumo-item saldo">
        <span class="rotulo">Saldo do mês</span>
        <span class="numero"><?= formatar_moeda($saldo) ?></span>
    </div>
</div>

<div class="cartao">
    <a href="/transacao_form.php" class="btn">+ Novo lançamento</a>
</div>

<div class="cartao">
    <h2 style="font-size:1.1rem;">Últimos lançamentos</h2>
    <?php if (!$ultimos): ?>
        <p class="vazio">Nenhum lançamento ainda. Comece adicionando o primeiro.</p>
    <?php else: ?>
    <table class="razao">
        <thead>
            <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Quem</th><th style="text-align:right;">Valor</th></tr>
        </thead>
        <tbody>
        <?php foreach ($ultimos as $t): ?>
            <tr>
                <td><?= formatar_data($t['data_transacao']) ?></td>
                <td><?= e($t['descricao']) ?: '<span style="color:#999;">—</span>' ?></td>
                <td><span class="selo" style="color: <?= e($t['categoria_cor']) ?>;"><?= e($t['categoria_nome']) ?></span></td>
                <td><?= e($t['usuario_nome']) ?></td>
                <td style="text-align:right;" class="valor-mono <?= $t['tipo']==='receita' ? 'valor-receita' : 'valor-despesa' ?>">
                    <?= $t['tipo']==='receita' ? '+' : '-' ?> <?= formatar_moeda($t['valor']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:1rem;"><a href="/transacoes.php">Ver todos os lançamentos →</a></p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
