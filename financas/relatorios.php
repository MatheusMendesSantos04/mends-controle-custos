<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$familia_id = $_SESSION['familia_id'];
$mes = $_GET['mes'] ?? date('Y-m');

// --- Evolução dos últimos 6 meses (receitas x despesas) ---
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(data_transacao, '%Y-%m') AS mes, tipo, SUM(valor) AS total
    FROM transacoes
    WHERE familia_id = ? AND data_transacao >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY mes, tipo
    ORDER BY mes ASC
");
$dataRef = $mes . '-01';
$stmt->execute([$familia_id, $dataRef]);
$linhas = $stmt->fetchAll();

// Monta os últimos 6 meses (mesmo os sem lançamento) para o eixo do gráfico
$meses_labels = [];
$receitas_serie = [];
$despesas_serie = [];
$mapa = [];
foreach ($linhas as $l) {
    $mapa[$l['mes']][$l['tipo']] = (float) $l['total'];
}
for ($i = 5; $i >= 0; $i--) {
    $referencia = date('Y-m', strtotime($dataRef . " -$i months"));
    $meses_labels[] = date('M/Y', strtotime($referencia . '-01'));
    $receitas_serie[] = $mapa[$referencia]['receita'] ?? 0;
    $despesas_serie[] = $mapa[$referencia]['despesa'] ?? 0;
}

// --- Gastos por categoria no mês selecionado ---
$stmt = $pdo->prepare("
    SELECT c.nome, c.cor, SUM(t.valor) AS total
    FROM transacoes t
    JOIN categorias c ON c.id = t.categoria_id
    WHERE t.familia_id = ? AND t.tipo = 'despesa' AND DATE_FORMAT(t.data_transacao, '%Y-%m') = ?
    GROUP BY c.id
    ORDER BY total DESC
");
$stmt->execute([$familia_id, $mes]);
$por_categoria = $stmt->fetchAll();

$cat_labels = array_column($por_categoria, 'nome');
$cat_valores = array_map('floatval', array_column($por_categoria, 'total'));
$cat_cores = array_column($por_categoria, 'cor');

$titulo_pagina = 'Relatórios';
require __DIR__ . '/includes/header.php';
?>

<h1>Relatórios</h1>

<div class="cartao">
    <form method="get" style="display:flex; gap:1rem; align-items:end;">
        <div>
            <label>Mês de referência (para o gráfico por categoria)</label>
            <input type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()">
        </div>
    </form>
</div>

<div class="cartao">
    <h2 style="font-size:1.05rem;">Receitas x despesas — últimos 6 meses</h2>
    <canvas id="graficoEvolucao" height="110"></canvas>
</div>

<div class="cartao">
    <h2 style="font-size:1.05rem;">Despesas por categoria — <?= e(date('m/Y', strtotime($mes . '-01'))) ?></h2>
    <?php if (!$cat_labels): ?>
        <p class="vazio">Nenhuma despesa registrada neste mês.</p>
    <?php else: ?>
        <canvas id="graficoCategorias" height="110"></canvas>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const corReceita = '#2F4F3E';
const corDespesa = '#A73C3C';

new Chart(document.getElementById('graficoEvolucao'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses_labels) ?>,
        datasets: [
            { label: 'Receitas', data: <?= json_encode($receitas_serie) ?>, backgroundColor: corReceita },
            { label: 'Despesas', data: <?= json_encode($despesas_serie) ?>, backgroundColor: corDespesa }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
    }
});

<?php if ($cat_labels): ?>
new Chart(document.getElementById('graficoCategorias'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($cat_labels) ?>,
        datasets: [{
            data: <?= json_encode($cat_valores) ?>,
            backgroundColor: <?= json_encode($cat_cores) ?>
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'right' } }
    }
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
