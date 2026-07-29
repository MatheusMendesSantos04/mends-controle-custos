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
$rosca = segmentos_rosca(array_map(fn($c) => ['nome' => $c['nome'], 'cor' => $c['cor'], 'valor' => (float)$c['total']], $por_categoria));

// --- Insights do mês ---

// 1) Meta: 20% da receita guardado/investido
$meta_investimento = $totais['receita'] * (META_INVESTIMENTO_PCT / 100);
$progresso_meta = $meta_investimento > 0 ? min(100, ($totais['investimento'] / $meta_investimento) * 100) : 0;

// 2) Variação das despesas vs mês anterior
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(valor),0) AS total FROM financas_transacoes
    WHERE usuario_id = ? AND tipo = 'despesa' AND DATE_FORMAT(data_transacao, '%Y-%m') = ?
");
$stmt->execute([$usuario_id, $mes_anterior]);
$despesa_mes_anterior = (float) $stmt->fetch()['total'];
$variacao_despesa = variacao_percentual($totais['despesa'], $despesa_mes_anterior);

// 3) Percentual da receita que sobrou (saldo), sem contar o que já foi investido
$percentual_sobra = $totais['receita'] > 0 ? ($saldo / $totais['receita']) * 100 : null;

// 4) Maior categoria de despesa do mês
$maior_categoria = $por_categoria[0] ?? null;
$maior_categoria_pct = ($maior_categoria && $totais['despesa'] > 0) ? ((float)$maior_categoria['total'] / $totais['despesa']) * 100 : null;

// 5) Comparação da maior categoria com a média dos últimos 3 meses (sem contar o mês atual)
$media_categoria = null;
$variacao_media_categoria = null;
if ($maior_categoria) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.valor),0) AS total
        FROM financas_transacoes t
        JOIN financas_categorias c ON c.id = t.categoria_id
        WHERE t.usuario_id = ? AND c.nome = ? AND t.tipo = 'despesa'
          AND t.data_transacao >= DATE_SUB(?, INTERVAL 3 MONTH) AND t.data_transacao < ?
    ");
    $stmt->execute([$usuario_id, $maior_categoria['nome'], $mes . '-01', $mes . '-01']);
    $soma_3_meses = (float) $stmt->fetch()['total'];
    $media_categoria = $soma_3_meses / 3;
    $variacao_media_categoria = variacao_percentual((float)$maior_categoria['total'], $media_categoria);
}

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

<div class="cabecalho-painel">
    <h1>Olá, <?= e($_SESSION['usuario_nome']) ?></h1>
    <div class="nav-mes">
        <a href="?mes=<?= e($mes_anterior) ?>" aria-label="Mês anterior">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <span class="mes-atual"><?= e($mes_extenso) ?></span>
        <a href="?mes=<?= e($mes_seguinte) ?>" aria-label="Próximo mês">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </a>
    </div>
</div>

<div class="grade-resumo">
    <div class="resumo-item receita">
        <span class="rotulo">Receitas</span>
        <div class="numero"><?= formatar_moeda($totais['receita']) ?></div>
    </div>
    <div class="resumo-item despesa">
        <span class="rotulo">Despesas</span>
        <div class="numero"><?= formatar_moeda($totais['despesa']) ?></div>
    </div>
    <div class="resumo-item investir">
        <span class="rotulo">Investido</span>
        <div class="numero"><?= formatar_moeda($totais['investimento']) ?></div>
    </div>
    <div class="resumo-item saldo <?= $saldo >= 0 ? 'positivo' : 'negativo' ?>">
        <span class="rotulo">Saldo</span>
        <div class="numero"><?= formatar_moeda($saldo) ?></div>
    </div>
</div>

<div class="cartao">
    <h2>Meta de investimento — <?= META_INVESTIMENTO_PCT ?>% da receita</h2>
    <div class="barra-progresso">
        <div class="barra-progresso-preenchida" style="width:<?= round($progresso_meta) ?>%; background:<?= $progresso_meta >= 100 ? 'var(--receita)' : 'var(--investir)' ?>;"></div>
    </div>
    <p style="margin:8px 0 0; font-size:13px; color:var(--color-neutral-700);">
        <?= e(formatar_moeda($totais['investimento'])) ?> de <?= e(formatar_moeda($meta_investimento)) ?>
        <strong style="color:var(--color-text);">(<?= round($progresso_meta) ?>%)</strong>
    </p>

    <div class="lista-insights">
        <div class="insight-item">
            <?php if ($variacao_despesa === null): ?>
                <span>Sem despesas no mês anterior para comparar.</span>
            <?php else: ?>
                <span>Você gastou
                    <strong style="color:<?= $variacao_despesa > 0 ? 'var(--despesa)' : 'var(--receita)' ?>;">
                        <?= number_format(abs($variacao_despesa), 0) ?>% <?= $variacao_despesa > 0 ? 'a mais' : 'a menos' ?>
                    </strong>
                    do que no mês anterior.
                </span>
            <?php endif; ?>
        </div>

        <div class="insight-item">
            <?php if ($percentual_sobra === null): ?>
                <span>Registre uma receita para calcular quanto sobrou.</span>
            <?php else: ?>
                <span>Sobrou
                    <strong style="color:<?= $percentual_sobra >= 0 ? 'var(--receita)' : 'var(--despesa)' ?>;">
                        <?= number_format($percentual_sobra, 0) ?>%
                    </strong>
                    da sua receita este mês (fora o que já foi investido).
                </span>
            <?php endif; ?>
        </div>

        <?php if ($maior_categoria): ?>
        <div class="insight-item">
            <span><strong style="color:var(--color-text);"><?= e($maior_categoria['nome']) ?></strong> foi seu maior gasto:
                <?= e(formatar_moeda((float)$maior_categoria['total'])) ?>
                <?php if ($maior_categoria_pct !== null): ?>(<?= round($maior_categoria_pct) ?>% do total)<?php endif; ?>.
            </span>
        </div>
        <?php if ($variacao_media_categoria !== null): ?>
        <div class="insight-item">
            <span><?= e($maior_categoria['nome']) ?> está
                <strong style="color:<?= $variacao_media_categoria > 0 ? 'var(--despesa)' : 'var(--receita)' ?>;">
                    <?= number_format(abs($variacao_media_categoria), 0) ?>% <?= $variacao_media_categoria > 0 ? 'acima' : 'abaixo' ?>
                </strong>
                da média dos últimos 3 meses.
            </span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div style="display:flex; justify-content:flex-end; margin-bottom:var(--space-6);">
    <a href="/transacao_form.php" class="btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Novo lançamento
    </a>
</div>

<div class="painel-grade">
    <div class="cartao">
        <h2>Lançamentos do mês</h2>
        <?php if (!$ultimos): ?>
            <p class="vazio">Nenhum lançamento neste mês. Comece adicionando o primeiro.</p>
        <?php else: ?>
        <div class="tabela-scroll">
        <table class="razao">
            <thead>
                <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th style="text-align:right;">Valor</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ultimos as $t): ?>
                <tr>
                    <td><?= formatar_data($t['data_transacao']) ?></td>
                    <td><?= e($t['descricao']) ?: '<span style="color:var(--color-neutral-700);">—</span>' ?></td>
                    <td><span class="selo" style="color: <?= e($t['categoria_cor']) ?>;"><span class="ponto"></span><?= e($t['categoria_nome']) ?></span></td>
                    <td style="text-align:right;" class="valor-mono valor-<?= e($t['tipo']) ?>">
                        <?= $t['tipo']==='despesa' ? '-' : '+' ?> <?= formatar_moeda($t['valor']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p style="margin-top:var(--space-3); font-size:14px;"><a href="/transacoes.php?mes=<?= e($mes) ?>">Ver todos os lançamentos →</a></p>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2>Despesas por categoria</h2>
        <?php if (!$rosca): ?>
            <p class="vazio">Nenhuma despesa registrada.</p>
        <?php else: ?>
            <div style="display:flex; justify-content:center; margin-bottom:var(--space-4);">
                <svg width="168" height="168" viewBox="0 0 168 168">
                    <g transform="translate(84,84) rotate(-90)">
                        <circle r="70" fill="none" stroke="var(--color-neutral-200)" stroke-width="26"/>
                        <?php foreach ($rosca as $seg): ?>
                            <circle r="70" fill="none" stroke="<?= e($seg['cor']) ?>" stroke-width="26"
                                stroke-dasharray="<?= e($seg['dasharray']) ?>" stroke-dashoffset="<?= e((string)$seg['dashoffset']) ?>" stroke-linecap="butt"></circle>
                        <?php endforeach; ?>
                    </g>
                    <text x="84" y="80" text-anchor="middle" font-family="Figtree, sans-serif" font-weight="700" font-size="18" fill="var(--color-text)"><?= e(formatar_moeda($totais['despesa'])) ?></text>
                    <text x="84" y="98" text-anchor="middle" font-size="10" fill="var(--color-neutral-700)">no mês</text>
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
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
