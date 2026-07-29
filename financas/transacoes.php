<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];

// Exclusão de lançamento
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare('DELETE FROM financas_transacoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$_GET['excluir'], $usuario_id]);
    header('Location: /transacoes.php' . (isset($_GET['mes']) ? '?mes=' . urlencode($_GET['mes']) : ''));
    exit;
}

// Filtro por mês (padrão: mês atual)
$mes = $_GET['mes'] ?? date('Y-m');

$stmt = $pdo->prepare("
    SELECT t.*, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM financas_transacoes t
    JOIN financas_categorias c ON c.id = t.categoria_id
    WHERE t.usuario_id = ? AND DATE_FORMAT(t.data_transacao, '%Y-%m') = ?
    ORDER BY t.data_transacao DESC, t.id DESC
");
$stmt->execute([$usuario_id, $mes]);
$transacoes = $stmt->fetchAll();

$titulo_pagina = 'Lançamentos';
require __DIR__ . '/includes/header.php';
?>

<h1>Lançamentos</h1>

<div class="cartao">
    <form method="get" style="display:flex; gap:1rem; align-items:end; flex-wrap:wrap;">
        <div>
            <label>Mês</label>
            <input type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()">
        </div>
        <a href="/transacao_form.php" class="btn" style="margin-top:0;">+ Novo lançamento</a>
    </form>
</div>

<div class="cartao">
    <?php if (!$transacoes): ?>
        <p class="vazio">Nenhum lançamento neste mês.</p>
    <?php else: ?>
    <table class="razao">
        <thead>
            <tr>
                <th>Data</th><th>Descrição</th><th>Categoria</th><th>Tipo</th>
                <th style="text-align:right;">Valor</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($transacoes as $t): ?>
            <tr>
                <td><?= formatar_data($t['data_transacao']) ?></td>
                <td><?= e($t['descricao']) ?: '<span style="color:var(--text-muted);">—</span>' ?></td>
                <td><span class="selo" style="color: <?= e($t['categoria_cor']) ?>;"><span class="ponto"></span><?= e($t['categoria_nome']) ?></span></td>
                <td><span class="selo-tipo <?= e($t['tipo']) ?>"><?= e(rotulo_tipo($t['tipo'])) ?></span></td>
                <td style="text-align:right;" class="valor-mono valor-<?= e($t['tipo']) ?>">
                    <?= $t['tipo']==='despesa' ? '-' : '+' ?> <?= formatar_moeda($t['valor']) ?>
                </td>
                <td class="acoes-tabela">
                    <a href="/transacao_form.php?id=<?= e((string)$t['id']) ?>">editar</a>
                    <a href="?excluir=<?= e((string)$t['id']) ?>&mes=<?= e($mes) ?>" class="excluir"
                       onclick="return confirm('Excluir este lançamento?');">excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
