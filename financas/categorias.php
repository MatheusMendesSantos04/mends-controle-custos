<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$aba = $_GET['tipo'] ?? 'despesa';
if (!in_array($aba, ['receita', 'despesa', 'investimento'], true)) {
    $aba = 'despesa';
}

// Exclusão
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM financas_transacoes WHERE categoria_id = ? AND usuario_id = ?');
    $stmt->execute([$_GET['excluir'], $usuario_id]);
    if ($stmt->fetch()['total'] > 0) {
        $erro = 'Não é possível excluir uma categoria que já tem lançamentos.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM financas_categorias WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$_GET['excluir'], $usuario_id]);
        header('Location: /categorias.php?tipo=' . urlencode($aba));
        exit;
    }
}

// Criação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'despesa';

    if ($nome === '') {
        $erro = 'Informe um nome para a categoria.';
    } else {
        $cor = proxima_cor_categoria($pdo, $usuario_id, $tipo);
        $stmt = $pdo->prepare('INSERT INTO financas_categorias (usuario_id, nome, tipo, cor) VALUES (?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $nome, $tipo, $cor]);
        header('Location: /categorias.php?tipo=' . urlencode($tipo));
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM financas_categorias WHERE usuario_id = ? AND tipo = ? ORDER BY ordem, nome');
$stmt->execute([$usuario_id, $aba]);
$categorias = $stmt->fetchAll();

// Tendência dos últimos 6 meses por categoria (sparkline)
$dataRef = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT categoria_id, DATE_FORMAT(data_transacao, '%Y-%m') AS mes, SUM(valor) AS total
    FROM financas_transacoes
    WHERE usuario_id = ? AND tipo = ? AND data_transacao >= DATE_SUB(?, INTERVAL 5 MONTH)
    GROUP BY categoria_id, mes
");
$stmt->execute([$usuario_id, $aba, $dataRef]);
$mensal_por_categoria = [];
foreach ($stmt->fetchAll() as $l) {
    $mensal_por_categoria[$l['categoria_id']][$l['mes']] = (float) $l['total'];
}
$meses_referencia = [];
for ($i = 5; $i >= 0; $i--) {
    $meses_referencia[] = date('Y-m', strtotime($dataRef . " -$i months"));
}
foreach ($categorias as &$c) {
    $valores = [];
    foreach ($meses_referencia as $m) {
        $valores[] = $mensal_por_categoria[$c['id']][$m] ?? 0;
    }
    $max_local = max(1, ...$valores);
    $c['sparkline'] = path_suave(escala_pontos($valores, 80, 24, $max_local));
    $c['total_6_meses'] = array_sum($valores);
}
unset($c);

$titulo_pagina = 'Categorias';
require __DIR__ . '/includes/header.php';
?>

<h1>Categorias</h1>

<?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

<div class="abas-tipo">
    <a href="?tipo=receita" class="<?= $aba==='receita' ? 'ativo' : '' ?>">Receitas</a>
    <a href="?tipo=despesa" class="<?= $aba==='despesa' ? 'ativo' : '' ?>">Despesas</a>
    <a href="?tipo=investimento" class="<?= $aba==='investimento' ? 'ativo' : '' ?>">Investir</a>
</div>

<div class="cartao" style="max-width:460px;">
    <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
        <input type="hidden" name="tipo" value="<?= e($aba) ?>">
        <div style="flex:1;">
            <label style="margin-top:0;">Nova categoria em <?= e(mb_strtolower(rotulo_tipo($aba))) ?></label>
            <input type="text" name="nome" placeholder="Ex: Pet, Academia..." required>
        </div>
        <button type="submit" class="btn" style="margin-top:0;">Adicionar</button>
    </form>
</div>

<div class="cartao">
    <?php if (!$categorias): ?>
        <p class="vazio">Nenhuma categoria de <?= mb_strtolower(e(rotulo_tipo($aba))) ?> ainda.</p>
    <?php else: ?>
    <div class="grade-categorias">
        <?php foreach ($categorias as $c): ?>
            <div class="cartao-categoria" style="flex-direction:column; align-items:stretch; gap:8px; --cor-cat: <?= e($c['cor']) ?>;">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span class="nome-cat"><?= e($c['nome']) ?></span>
                    <a href="?tipo=<?= e($aba) ?>&excluir=<?= e((string)$c['id']) ?>" class="excluir"
                       onclick="return confirm('Excluir esta categoria?');">excluir</a>
                </div>
                <?php if ($c['total_6_meses'] > 0): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                    <svg width="80" height="24" viewBox="0 0 80 24" class="sparkline">
                        <path d="<?= e($c['sparkline']) ?>" fill="none" stroke="<?= e($c['cor']) ?>" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span style="font-size:12px; color:var(--color-neutral-700); white-space:nowrap;">
                        <?= e(formatar_moeda($c['total_6_meses'])) ?> / 6m
                    </span>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
