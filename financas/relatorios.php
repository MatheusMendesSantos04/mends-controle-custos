<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$mes = $_GET['mes'] ?? date('Y-m');
$dataRef = $mes . '-01';
$nomes_curtos = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

// --- Evolução dos últimos 6 meses (receita x despesa x investimento) ---
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(data_transacao, '%Y-%m') AS mes, tipo, SUM(valor) AS total
    FROM financas_transacoes
    WHERE usuario_id = ? AND data_transacao >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY mes, tipo
    ORDER BY mes ASC
");
$stmt->execute([$usuario_id, $dataRef]);
$linhas = $stmt->fetchAll();

$mapa = [];
foreach ($linhas as $l) {
    $mapa[$l['mes']][$l['tipo']] = (float) $l['total'];
}
$meses_serie = [];
for ($i = 5; $i >= 0; $i--) {
    $referencia = date('Y-m', strtotime($dataRef . " -$i months"));
    $meses_serie[] = [
        'mes' => $referencia,
        'label' => $nomes_curtos[(int)date('n', strtotime($referencia . '-01')) - 1],
        'receita' => $mapa[$referencia]['receita'] ?? 0,
        'despesa' => $mapa[$referencia]['despesa'] ?? 0,
        'investimento' => $mapa[$referencia]['investimento'] ?? 0,
    ];
}

// Monta os pontos SVG (escala compartilhada entre as 3 séries)
$LARGURA_GRAFICO = 600;
$ALTURA_GRAFICO = 180;
$max_valor_grafico = max(1, ...array_map(fn($m) => max($m['receita'], $m['despesa'], $m['investimento']), $meses_serie));
$pontos_receita = escala_pontos(array_column($meses_serie, 'receita'), $LARGURA_GRAFICO, $ALTURA_GRAFICO, $max_valor_grafico);
$pontos_despesa = escala_pontos(array_column($meses_serie, 'despesa'), $LARGURA_GRAFICO, $ALTURA_GRAFICO, $max_valor_grafico);
$pontos_investimento = escala_pontos(array_column($meses_serie, 'investimento'), $LARGURA_GRAFICO, $ALTURA_GRAFICO, $max_valor_grafico);

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
$total_despesa_mes = array_sum(array_column($por_categoria, 'total'));
$rosca = segmentos_rosca(array_map(fn($c) => ['nome' => $c['nome'], 'cor' => $c['cor'], 'valor' => (float)$c['total']], $por_categoria));

// --- Totais por categoria nos últimos 6 meses (com tendência/sparkline) ---
$stmt = $pdo->prepare("
    SELECT c.id, c.nome, c.cor, DATE_FORMAT(t.data_transacao, '%Y-%m') AS mes, SUM(t.valor) AS total
    FROM financas_transacoes t
    JOIN financas_categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND t.tipo = 'despesa' AND t.data_transacao >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY c.id, mes
    ORDER BY c.nome
");
$stmt->execute([$usuario_id, $dataRef]);
$linhas_categoria = $stmt->fetchAll();

$por_categoria_mensal = [];
foreach ($linhas_categoria as $l) {
    $cid = $l['id'];
    if (!isset($por_categoria_mensal[$cid])) {
        $por_categoria_mensal[$cid] = ['nome' => $l['nome'], 'cor' => $l['cor'], 'valores' => []];
    }
    $por_categoria_mensal[$cid]['valores'][$l['mes']] = (float) $l['total'];
}

$totais_categoria = [];
foreach ($por_categoria_mensal as $cid => $info) {
    $valores = [];
    foreach ($meses_serie as $m) {
        $valores[] = $info['valores'][$m['mes']] ?? 0;
    }
    $total_periodo = array_sum($valores);
    $max_local = max(1, ...$valores);
    $pontos_spark = escala_pontos($valores, 80, 24, $max_local);
    $totais_categoria[] = [
        'nome' => $info['nome'],
        'cor' => $info['cor'],
        'total' => $total_periodo,
        'media' => $total_periodo / 6,
        'sparkline' => path_suave($pontos_spark),
    ];
}
usort($totais_categoria, fn($a, $b) => $b['total'] <=> $a['total']);

// --- Comprometimento por cartão (mês atual em diante) ---
$stmt = $pdo->prepare('
    SELECT c.id, c.nome, c.limite, COALESCE(SUM(t.valor), 0) AS comprometido
    FROM financas_cartoes c
    LEFT JOIN financas_transacoes t ON t.cartao_id = c.id AND t.tipo = "despesa" AND t.data_transacao >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
    WHERE c.usuario_id = ? AND c.ativo = 1
    GROUP BY c.id
    ORDER BY c.nome
');
$stmt->execute([$usuario_id]);
$cartoes_relatorio = $stmt->fetchAll();

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
    <div class="grafico-linha-wrap" id="grafico-evolucao">
        <svg viewBox="0 0 600 200" preserveAspectRatio="none" class="grafico-linha-svg">
            <line x1="0" y1="180" x2="600" y2="180" stroke="var(--color-divider)" stroke-width="1"/>
            <path d="<?= e(path_suave($pontos_receita)) ?>" fill="none" stroke="var(--receita)" stroke-width="2.5" stroke-linecap="round"/>
            <path d="<?= e(path_suave($pontos_despesa)) ?>" fill="none" stroke="var(--despesa)" stroke-width="2.5" stroke-linecap="round"/>
            <path d="<?= e(path_suave($pontos_investimento)) ?>" fill="none" stroke="var(--investir)" stroke-width="2.5" stroke-linecap="round"/>
            <?php foreach ($pontos_receita as $p): ?><circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3.5" fill="var(--receita)"/><?php endforeach; ?>
            <?php foreach ($pontos_despesa as $p): ?><circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3.5" fill="var(--despesa)"/><?php endforeach; ?>
            <?php foreach ($pontos_investimento as $p): ?><circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="3.5" fill="var(--investir)"/><?php endforeach; ?>
            <line id="linha-cursor" x1="0" y1="0" x2="0" y2="180" stroke="var(--color-neutral-700)" stroke-width="1" stroke-dasharray="3 3" style="display:none;"/>
            <?php foreach ($meses_serie as $i => $m): ?>
                <rect class="area-hover" data-indice="<?= $i ?>" x="<?= $i * 100 ?>" y="0" width="100" height="200" fill="transparent"></rect>
            <?php endforeach; ?>
        </svg>
        <div class="grafico-tooltip" id="tooltip-evolucao" style="display:none;"></div>
        <div class="grafico-eixo-x">
            <?php foreach ($meses_serie as $m): ?><span><?= e($m['label']) ?></span><?php endforeach; ?>
        </div>
    </div>
</div>

<div class="painel-grade">
    <div class="cartao">
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
                    <text x="84" y="80" text-anchor="middle" font-family="Figtree, sans-serif" font-weight="700" font-size="18" fill="var(--color-text)"><?= e(formatar_moeda($total_despesa_mes)) ?></text>
                    <text x="84" y="98" text-anchor="middle" font-size="10" fill="var(--color-neutral-700)">no mês</text>
                </svg>
            </div>
            <div class="legenda-doughnut">
                <?php foreach ($rosca as $seg): ?>
                    <div class="item">
                        <span class="rotulo-item"><span class="ponto" style="background:<?= e($seg['cor']) ?>;"></span><?= e($seg['nome']) ?></span>
                        <span class="pct"><?= e(formatar_moeda($seg['valor'] ?? 0)) ?> · <?= $seg['pct'] ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2>Totais por categoria — últimos 6 meses</h2>
        <?php if (!$totais_categoria): ?>
            <p class="vazio">Nenhuma despesa nos últimos 6 meses.</p>
        <?php else: ?>
        <div class="tabela-scroll">
        <table class="razao">
            <thead>
                <tr><th>Categoria</th><th>Tendência</th><th style="text-align:right;">Total</th><th style="text-align:right;">Média/mês</th></tr>
            </thead>
            <tbody>
                <?php foreach ($totais_categoria as $tc): ?>
                    <tr>
                        <td><span class="selo" style="color: <?= e($tc['cor']) ?>;"><span class="ponto"></span><?= e($tc['nome']) ?></span></td>
                        <td>
                            <svg width="80" height="24" viewBox="0 0 80 24" class="sparkline">
                                <path d="<?= e($tc['sparkline']) ?>" fill="none" stroke="<?= e($tc['cor']) ?>" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </td>
                        <td style="text-align:right;" class="valor-mono"><?= e(formatar_moeda($tc['total'])) ?></td>
                        <td style="text-align:right; color:var(--color-neutral-700);" class="valor-mono"><?= e(formatar_moeda($tc['media'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($cartoes_relatorio): ?>
<div class="cartao">
    <h2>Comprometimento por cartão</h2>
    <div class="grade-categorias">
        <?php foreach ($cartoes_relatorio as $c): ?>
            <?php $pct_uso = $c['limite'] > 0 ? min(100, ((float)$c['comprometido'] / (float)$c['limite']) * 100) : 0; ?>
            <div class="cartao-categoria" style="flex-direction:column; align-items:stretch; gap:6px; --cor-cat: var(--color-accent);">
                <span class="nome-cat"><?= e($c['nome']) ?></span>
                <div class="barra-progresso">
                    <div class="barra-progresso-preenchida" style="width:<?= round($pct_uso) ?>%; background:<?= $pct_uso >= 90 ? 'var(--despesa)' : 'var(--color-accent)' ?>;"></div>
                </div>
                <span style="font-size:12px; color:var(--color-neutral-700);">
                    <?= e(formatar_moeda((float)$c['comprometido'])) ?> de <?= e(formatar_moeda((float)$c['limite'])) ?> (<?= round($pct_uso) ?>%)
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const dados = <?= json_encode(array_map(fn($m) => [
        'label' => $m['label'],
        'receita' => formatar_moeda($m['receita']),
        'despesa' => formatar_moeda($m['despesa']),
        'investimento' => formatar_moeda($m['investimento']),
    ], $meses_serie)) ?>;
    const pontosX = <?= json_encode(array_column($pontos_receita, 'x')) ?>;

    const wrap = document.getElementById('grafico-evolucao');
    const tooltip = document.getElementById('tooltip-evolucao');
    const cursor = document.getElementById('linha-cursor');
    if (!wrap) return;

    wrap.querySelectorAll('.area-hover').forEach(area => {
        area.addEventListener('mouseenter', () => {
            const i = parseInt(area.dataset.indice, 10);
            const d = dados[i];
            tooltip.innerHTML =
                '<strong>' + d.label + '</strong>' +
                '<div><span style="color:var(--receita);">●</span> Receita: ' + d.receita + '</div>' +
                '<div><span style="color:var(--despesa);">●</span> Despesa: ' + d.despesa + '</div>' +
                '<div><span style="color:var(--investir);">●</span> Investido: ' + d.investimento + '</div>';
            tooltip.style.display = 'block';

            const pctX = pontosX[i] / 600;
            const wrapRect = wrap.getBoundingClientRect();
            let left = pctX * wrapRect.width - tooltip.offsetWidth / 2;
            left = Math.max(0, Math.min(left, wrapRect.width - tooltip.offsetWidth));
            tooltip.style.left = left + 'px';
            tooltip.style.top = '0px';

            cursor.setAttribute('x1', pontosX[i]);
            cursor.setAttribute('x2', pontosX[i]);
            cursor.style.display = 'block';
        });
        area.addEventListener('mouseleave', () => {
            tooltip.style.display = 'none';
            cursor.style.display = 'none';
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
