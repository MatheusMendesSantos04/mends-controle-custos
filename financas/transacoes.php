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

<div class="cabecalho-painel">
    <h1>Lançamentos</h1>
    <div style="display:flex; gap:10px; align-items:center;">
        <form method="get" id="form-mes">
            <input type="month" name="mes" value="<?= e($mes) ?>" onchange="this.form.submit()" style="padding:9px 14px;">
        </form>
        <a href="/transacao_form.php" class="btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Novo lançamento
        </a>
    </div>
</div>

<div class="cartao">
    <?php if (!$transacoes): ?>
        <p class="vazio">Nenhum lançamento neste mês.</p>
    <?php else: ?>
    <div class="tabela-scroll">
    <table class="razao">
        <thead>
            <tr>
                <th>Data</th><th>Descrição</th><th>Categoria</th><th>Tipo</th>
                <th style="text-align:right;">Valor</th><th style="text-align:right;">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($transacoes as $t): ?>
            <tr>
                <td><?= formatar_data($t['data_transacao']) ?></td>
                <td><?= e($t['descricao']) ?: '<span style="color:var(--color-neutral-700);">—</span>' ?></td>
                <td><span class="selo" style="color: <?= e($t['categoria_cor']) ?>;"><span class="ponto"></span><?= e($t['categoria_nome']) ?></span></td>
                <td><span class="selo-tipo <?= e($t['tipo']) ?>"><?= e(rotulo_tipo($t['tipo'])) ?></span></td>
                <td style="text-align:right;" class="valor-mono valor-<?= e($t['tipo']) ?>">
                    <?= $t['tipo']==='despesa' ? '-' : '+' ?> <?= formatar_moeda($t['valor']) ?>
                </td>
                <td>
                    <span class="acoes-tabela">
                        <a href="/transacao_form.php?id=<?= e((string)$t['id']) ?>" class="btn-icone" aria-label="Editar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        </a>
                        <a href="?excluir=<?= e((string)$t['id']) ?>&mes=<?= e($mes) ?>" class="btn-icone" aria-label="Excluir"
                           onclick="return confirm('Excluir este lançamento?');">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </a>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
