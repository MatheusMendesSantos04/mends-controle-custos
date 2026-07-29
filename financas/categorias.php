<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$familia_id = $_SESSION['familia_id'];
$erro = '';

// Exclusão
if (isset($_GET['excluir'])) {
    // Impede excluir categoria que já tem lançamentos, para não perder o histórico
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM transacoes WHERE categoria_id = ? AND familia_id = ?');
    $stmt->execute([$_GET['excluir'], $familia_id]);
    if ($stmt->fetch()['total'] > 0) {
        $erro = 'Não é possível excluir uma categoria que já tem lançamentos.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = ? AND familia_id = ?');
        $stmt->execute([$_GET['excluir'], $familia_id]);
    }
}

// Criação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'despesa';
    $cor  = $_POST['cor'] ?? '#2F4F3E';

    if ($nome === '') {
        $erro = 'Informe um nome para a categoria.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO categorias (familia_id, nome, tipo, cor) VALUES (?, ?, ?, ?)');
        $stmt->execute([$familia_id, $nome, $tipo, $cor]);
        header('Location: /categorias.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT * FROM categorias WHERE familia_id = ? ORDER BY tipo, nome');
$stmt->execute([$familia_id]);
$categorias = $stmt->fetchAll();

$titulo_pagina = 'Categorias';
require __DIR__ . '/includes/header.php';
?>

<h1>Categorias</h1>

<?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

<div class="cartao" style="max-width:520px;">
    <h2 style="font-size:1.05rem;">Nova categoria</h2>
    <form method="post">
        <div class="linha-form">
            <div>
                <label>Nome</label>
                <input type="text" name="nome" required>
            </div>
            <div>
                <label>Tipo</label>
                <select name="tipo">
                    <option value="despesa">Despesa</option>
                    <option value="receita">Receita</option>
                </select>
            </div>
        </div>
        <label>Cor (usada nas etiquetas)</label>
        <input type="color" name="cor" value="#2F4F3E" style="height:2.6rem; padding:0.2rem;">
        <button type="submit" class="btn">Adicionar categoria</button>
    </form>
</div>

<div class="cartao">
    <table class="razao">
        <thead><tr><th>Categoria</th><th>Tipo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categorias as $c): ?>
            <tr>
                <td><span class="selo" style="color: <?= e($c['cor']) ?>;"><?= e($c['nome']) ?></span></td>
                <td><?= $c['tipo']==='receita' ? 'Receita' : 'Despesa' ?></td>
                <td class="acoes-tabela">
                    <a href="?excluir=<?= e((string)$c['id']) ?>" class="excluir"
                       onclick="return confirm('Excluir esta categoria?');">excluir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
