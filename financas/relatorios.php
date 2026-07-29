<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('Y-m');

// --- Evolução dos últimos 6 meses (receita x despesa x investimento) ---
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(data_transacao, '%Y-%m') AS mes, tipo, SUM(valor) AS total
    FROM financas_transacoes
    WHERE usuario_id = ? AND data_transacao >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY mes, tipo
    ORDER BY mes ASC
");
$dataRef = $mes . '-01';
$stmt->execute([$usuario_id, $dataRef]);
$linhas = $stmt->fetchAll();

$meses_labels = [];
$receitas_serie = [];
$despesas_serie = [];
$investimentos_serie = [];
$mapa = [];
foreach ($linhas as $l) {
    $mapa[$l['mes']][$l['tipo']] = (float) $l['total'];
}
for ($i = 5; $i >= 0; $i--) {
    $referencia = date('Y-m', strtotime($dataRef . " -$i months"));
    $meses_labels[] = date('M/Y', strtotime($referencia . '-01'));
    $receitas_serie[] = $mapa[$referencia]['receita'] ?? 0;
    $despesas_serie[] = $mapa[$referencia]['despesa'] ?? 0;
    $investimentos_serie[] = $mapa[$referencia]['investimento'] ?? 0;
}

// --- Despesas por categoria no mês selecionado ---
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
    <h2 style="font-size:1.05rem;">Receitas, despesas e investimentos — últimos 6 meses</h2>
    <canvas id="graficoEvolucao" height="110"></canvas>
</div>

<div class="cartao">
    <h2 style="font-size:1.05rem;">Despesas por categoria — <?= e(date('m/Y', strtotime($mes . '-01'))) ?></h2>
    <?php if (!$cat_labels): ?>
        <p class="vazio">Nenhuma despesa registrada neste mês.</p>
    <?php else: ?>
        <canvas id="graficoCategorias" height="110"></canvas>
        <div class="legenda-doughnut" id="legendaCategorias"></div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const estilo = getComputedStyle(document.body);
const corReceita = estilo.getPropertyValue('--receita').trim();
const corDespesa = estilo.getPropertyValue('--despesa').trim();
const corInvestir = estilo.getPropertyValue('--investir').trim();
const corGrid = estilo.getPropertyValue('--gridline').trim();
const corTexto = estilo.getPropertyValue('--text-secondary').trim();

new Chart(document.getElementById('graficoEvolucao'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses_labels) ?>,
        datasets: [
            { label: 'Receitas', data: <?= json_encode($receitas_serie) ?>, backgroundColor: corReceita, borderRadius: 4 },
            { label: 'Despesas', data: <?= json_encode($despesas_serie) ?>, backgroundColor: corDespesa, borderRadius: 4 },
            { label: 'Investido', data: <?= json_encode($investimentos_serie) ?>, backgroundColor: corInvestir, borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { color: corTexto } } },
        scales: {
            y: { beginAtZero: true, grid: { color: corGrid }, ticks: { color: corTexto } },
            x: { grid: { display: false }, ticks: { color: corTexto } }
        }
    }
});

<?php if ($cat_labels): ?>
const labelsCat = <?= json_encode($cat_labels) ?>;
const coresCat = <?= json_encode($cat_cores) ?>;
new Chart(document.getElementById('graficoCategorias'), {
    type: 'doughnut',
    data: {
        labels: labelsCat,
        datasets: [{
            data: <?= json_encode($cat_valores) ?>,
            backgroundColor: coresCat,
            borderWidth: 2,
            borderColor: estilo.getPropertyValue('--surface').trim()
        }]
    },
    options: { responsive: true, cutout: '65%', plugins: { legend: { display: false } } }
});
const legenda = document.getElementById('legendaCategorias');
labelsCat.forEach((rotulo, i) => {
    const item = document.createElement('span');
    item.className = 'item';
    item.innerHTML = `<span class="ponto" style="background:${coresCat[i]}"></span>${rotulo}`;
    legenda.appendChild(item);
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
