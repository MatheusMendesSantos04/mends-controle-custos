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
        $cor = proxima_cor_categoria($pdo, $usuario_id);
        $stmt = $pdo->prepare('INSERT INTO financas_categorias (usuario_id, nome, tipo, cor) VALUES (?, ?, ?, ?)');
        $stmt->execute([$usuario_id, $nome, $tipo, $cor]);
        header('Location: /categorias.php?tipo=' . urlencode($tipo));
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM financas_categorias WHERE usuario_id = ? AND tipo = ? ORDER BY ordem, nome');
$stmt->execute([$usuario_id, $aba]);
$categorias = $stmt->fetchAll();

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

<div class="cartao" style="max-width:480px;">
    <h2 style="font-size:1.05rem;">Nova categoria em <?= e(rotulo_tipo($aba)) ?></h2>
    <form method="post">
        <input type="hidden" name="tipo" value="<?= e($aba) ?>">
        <label>Nome</label>
        <input type="text" name="nome" placeholder="Ex: Pet, Academia..." required>
        <button type="submit" class="btn">+ Adicionar categoria</button>
    </form>
</div>

<div class="cartao">
    <?php if (!$categorias): ?>
        <p class="vazio">Nenhuma categoria de <?= mb_strtolower(e(rotulo_tipo($aba))) ?> ainda.</p>
    <?php else: ?>
    <div class="grade-categorias">
        <?php foreach ($categorias as $c): ?>
            <div class="cartao-categoria" style="--cor-cat: <?= e($c['cor']) ?>;">
                <span class="nome-cat"><?= e($c['nome']) ?></span>
                <a href="?tipo=<?= e($aba) ?>&excluir=<?= e((string)$c['id']) ?>" class="excluir"
                   onclick="return confirm('Excluir esta categoria?');">excluir</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
