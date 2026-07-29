<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('Y-m');
$mes_anterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mes_seguinte = date('Y-m', strtotime($mes . '-01 +1 month'));

// Totais do mês selecionado
$stmt = $pdo->prepare("
    SELECT tipo, COALESCE(SUM(valor),0) AS total
    FROM financas_transacoes
    WHERE usuario_id = ? AND DATE_FORMAT(data_transacao, '%Y-%m') = ?
    GROUP BY tipo
");
$stmt->execute([$usuario_id, $mes]);
$totais = ['receita' => 0, 'despesa' => 0, 'investimento' => 0];
foreach ($stmt->fetchAll() as $linha) {
    $totais[$linha['tipo']] = (float) $linha['total'];
}
$saldo = $totais['receita'] - $totais['despesa'] - $totais['investimento'];

// Despesas por categoria no mês (para o gráfico de rosca)
$stmt = $pdo->prepare("
    SELECT c.nome, c.cor, SUM(t.valor) AS total
    FROM financas_transacoes t
    JOIN financas_categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND DATE_FORMAT(t.data_transacao, '%Y-%m') = ?
    GROUP BY c.id
    ORDER BY total DESC
");
$stmt->execute([$usuario_id, $mes]);
$por_categoria = $stmt->fetchAll();
$cat_labels = array_column($por_categoria, 'nome');
$cat_valores = array_map('floatval', array_column($por_categoria, 'total'));
$cat_cores = array_column($por_categoria, 'cor');

// Últimos lançamentos do mês
$stmt = $pdo->prepare("
    SELECT t.*, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM financas_transacoes t
    JOIN financas_categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND DATE_FORMAT(t.data_transacao, '%Y-%m') = ?
    ORDER BY t.data_transacao DESC, t.id DESC
    LIMIT 8
");
$stmt->execute([$usuario_id, $mes]);
$ultimos = $stmt->fetchAll();

$nomes_meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$mes_extenso = $nomes_meses[(int)date('n', strtotime($mes . '-01')) - 1] . ' de ' . date('Y', strtotime($mes . '-01'));

$titulo_pagina = 'Painel';
require __DIR__ . '/includes/header.php';
?>

<h1>Olá, <?= e($_SESSION['usuario_nome']) ?> 👋</h1>

<div class="nav-mes">
    <a href="?mes=<?= e($mes_anterior) ?>" aria-label="Mês anterior">‹</a>
    <span class="mes-atual"><?= e($mes_extenso) ?></span>
    <a href="?mes=<?= e($mes_seguinte) ?>" aria-label="Próximo mês">›</a>
</div>

<div class="grade-resumo">
    <div class="resumo-item receita">
        <span class="rotulo">Receitas</span>
        <span class="numero"><?= formatar_moeda($totais['receita']) ?></span>
    </div>
    <div class="resumo-item despesa">
        <span class="rotulo">Despesas</span>
        <span class="numero"><?= formatar_moeda($totais['despesa']) ?></span>
    </div>
    <div class="resumo-item investir">
        <span class="rotulo">Investido</span>
        <span class="numero"><?= formatar_moeda($totais['investimento']) ?></span>
    </div>
    <div class="resumo-item saldo <?= $saldo >= 0 ? 'positivo' : 'negativo' ?>">
        <span class="rotulo">Saldo</span>
        <span class="numero"><?= formatar_moeda($saldo) ?></span>
    </div>
</div>

<div class="cartao" style="text-align:center;">
    <a href="/transacao_form.php" class="btn">+ Novo lançamento</a>
</div>

<?php if ($cat_labels): ?>
<div class="cartao">
    <h2 style="font-size:1.05rem;">Despesas por categoria</h2>
    <canvas id="graficoCategorias" height="200"></canvas>
    <div class="legenda-doughnut" id="legendaCategorias"></div>
</div>
<?php endif; ?>

<div class="cartao">
    <h2 style="font-size:1.1rem;">Lançamentos do mês</h2>
    <?php if (!$ultimos): ?>
        <p class="vazio">Nenhum lançamento neste mês. Comece adicionando o primeiro.</p>
    <?php else: ?>
    <table class="razao">
        <thead>
            <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th style="text-align:right;">Valor</th></tr>
        </thead>
        <tbody>
        <?php foreach ($ultimos as $t): ?>
            <tr>
                <td><?= formatar_data($t['data_transacao']) ?></td>
                <td><?= e($t['descricao']) ?: '<span style="color:var(--text-muted);">—</span>' ?></td>
                <td><span class="selo" style="color: <?= e($t['categoria_cor']) ?>;"><span class="ponto"></span><?= e($t['categoria_nome']) ?></span></td>
                <td style="text-align:right;" class="valor-mono valor-<?= e($t['tipo']) ?>">
                    <?= $t['tipo']==='despesa' ? '-' : '+' ?> <?= formatar_moeda($t['valor']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:1rem;"><a href="/transacoes.php?mes=<?= e($mes) ?>">Ver todos os lançamentos →</a></p>
    <?php endif; ?>
</div>

<?php if ($cat_labels): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels = <?= json_encode($cat_labels) ?>;
const valores = <?= json_encode($cat_valores) ?>;
const cores = <?= json_encode($cat_cores) ?>;

new Chart(document.getElementById('graficoCategorias'), {
    type: 'doughnut',
    data: {
        labels,
        datasets: [{ data: valores, backgroundColor: cores, borderWidth: 2, borderColor: getComputedStyle(document.body).getPropertyValue('--surface') }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});

const legenda = document.getElementById('legendaCategorias');
labels.forEach((rotulo, i) => {
    const item = document.createElement('span');
    item.className = 'item';
    item.innerHTML = `<span class="ponto" style="background:${cores[i]}"></span>${rotulo}`;
    legenda.appendChild(item);
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
