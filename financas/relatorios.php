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

$mapa = [];
foreach ($linhas as $l) {
    $mapa[$l['mes']][$l['tipo']] = (float) $l['total'];
}
$meses_barras = [];
$nomes_curtos = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
for ($i = 5; $i >= 0; $i--) {
    $referencia = date('Y-m', strtotime($dataRef . " -$i months"));
    $meses_barras[] = [
        'label' => $nomes_curtos[(int)date('n', strtotime($referencia . '-01')) - 1],
        'receita' => $mapa[$referencia]['receita'] ?? 0,
        'despesa' => $mapa[$referencia]['despesa'] ?? 0,
        'investimento' => $mapa[$referencia]['investimento'] ?? 0,
    ];
}
$maxBarra = max(1, ...array_map(fn($m) => max($m['receita'], $m['despesa'], $m['investimento']), $meses_barras));
foreach ($meses_barras as &$m) {
    $m['receitaH'] = round(($m['receita'] / $maxBarra) * 180);
    $m['despesaH'] = round(($m['despesa'] / $maxBarra) * 180);
    $m['investimentoH'] = round(($m['investimento'] / $maxBarra) * 180);
}
unset($m);

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
$rosca = segmentos_rosca(array_map(fn($c) => ['nome' => $c['nome'], 'cor' => $c['cor'], 'valor' => (float)$c['total']], $por_categoria));

$titulo_pagina = 'Relatórios';
require __DIR__ . '/includes/header.php';
?>

<div class="cabecalho-painel">
    <h1>Relatórios</h1>
    <form method="get">
        <input type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()" style="padding:9px 14px;">
    </form>
</div>

<div class="cartao">
    <h2>Receita x Despesa x Investido — últimos 6 meses</h2>
    <div class="legenda-barras">
        <span class="item"><span class="ponto" style="background:var(--receita);"></span>Receita</span>
        <span class="item"><span class="ponto" style="background:var(--despesa);"></span>Despesa</span>
        <span class="item"><span class="ponto" style="background:var(--investir);"></span>Investido</span>
    </div>
    <div class="grafico-barras">
        <?php foreach ($meses_barras as $m): ?>
            <div class="coluna-mes">
                <div class="barras">
                    <div class="barra" style="height:<?= $m['receitaH'] ?>px; background:var(--receita);"></div>
                    <div class="barra" style="height:<?= $m['despesaH'] ?>px; background:var(--despesa);"></div>
                    <div class="barra" style="height:<?= $m['investimentoH'] ?>px; background:var(--investir);"></div>
                </div>
                <span class="rotulo-mes"><?= e($m['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="cartao" style="max-width:420px;">
    <h2>Despesas por categoria do mês</h2>
    <?php if (!$rosca): ?>
        <p class="vazio">Nenhuma despesa registrada.</p>
    <?php else: ?>
        <div style="display:flex; justify-content:center; margin-bottom:var(--space-4);">
            <svg width="168" height="168" viewBox="0 0 168 168">
                <g transform="translate(84,84) rotate(-90)">
                    <circle r="70" fill="none" stroke="var(--color-neutral-200)" stroke-width="26"/>
                    <?php foreach ($rosca as $seg): ?>
                        <circle r="70" fill="none" stroke="<?= e($seg['cor']) ?>" stroke-width="26"
                            stroke-dasharray="<?= e($seg['dasharray']) ?>" stroke-dashoffset="<?= e((string)$seg['dashoffset']) ?>"></circle>
                    <?php endforeach; ?>
                </g>
            </svg>
        </div>
        <div class="legenda-doughnut">
            <?php foreach ($rosca as $seg): ?>
                <div class="item">
                    <span class="rotulo-item"><span class="ponto" style="background:<?= e($seg['cor']) ?>;"></span><?= e($seg['nome']) ?></span>
                    <span class="pct"><?= $seg['pct'] ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
