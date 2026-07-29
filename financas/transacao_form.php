<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$familia_id = $_SESSION['familia_id'];
$erro = '';
$edicao = false;

// Dados padrão do formulário
$dados = [
    'id' => null,
    'tipo' => 'despesa',
    'valor' => '',
    'categoria_id' => '',
    'descricao' => '',
    'data_transacao' => date('Y-m-d'),
];

// Se veio um ID pela URL, carrega o lançamento para edição
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM transacoes WHERE id = ? AND familia_id = ?');
    $stmt->execute([$_GET['id'], $familia_id]);
    $existente = $stmt->fetch();
    if ($existente) {
        $dados = $existente;
        $edicao = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados['tipo']            = $_POST['tipo'] ?? 'despesa';
    $dados['valor']           = str_replace(',', '.', $_POST['valor'] ?? '');
    $dados['categoria_id']    = $_POST['categoria_id'] ?? '';
    $dados['descricao']       = trim($_POST['descricao'] ?? '');
    $dados['data_transacao']  = $_POST['data_transacao'] ?? date('Y-m-d');
    $id_edicao                = $_POST['id'] ?? null;

    if (!is_numeric($dados['valor']) || (float)$dados['valor'] <= 0) {
        $erro = 'Informe um valor válido, maior que zero.';
    } elseif (!$dados['categoria_id']) {
        $erro = 'Selecione uma categoria.';
    } else {
        if ($id_edicao) {
            $stmt = $pdo->prepare("
                UPDATE transacoes
                SET tipo=?, valor=?, categoria_id=?, descricao=?, data_transacao=?
                WHERE id=? AND familia_id=?
            ");
            $stmt->execute([
                $dados['tipo'], $dados['valor'], $dados['categoria_id'],
                $dados['descricao'], $dados['data_transacao'], $id_edicao, $familia_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (familia_id, usuario_id, categoria_id, tipo, valor, descricao, data_transacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $familia_id, $_SESSION['usuario_id'], $dados['categoria_id'],
                $dados['tipo'], $dados['valor'], $dados['descricao'], $dados['data_transacao']
            ]);
        }
        header('Location: /transacoes.php');
        exit;
    }
}

// Busca categorias da família para popular o select
$stmt = $pdo->prepare('SELECT id, nome, tipo FROM categorias WHERE familia_id = ? ORDER BY tipo, nome');
$stmt->execute([$familia_id]);
$categorias = $stmt->fetchAll();

$titulo_pagina = $edicao ? 'Editar lançamento' : 'Novo lançamento';
require __DIR__ . '/includes/header.php';
?>

<h1><?= $edicao ? 'Editar lançamento' : 'Novo lançamento' ?></h1>

<div class="cartao" style="max-width:520px;">
    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="post">
        <?php if ($dados['id']): ?><input type="hidden" name="id" value="<?= e((string)$dados['id']) ?>"><?php endif; ?>

        <label>Tipo</label>
        <select name="tipo" id="tipo" required>
            <option value="despesa" <?= $dados['tipo']==='despesa' ? 'selected' : '' ?>>Despesa</option>
            <option value="receita" <?= $dados['tipo']==='receita' ? 'selected' : '' ?>>Receita</option>
        </select>

        <div class="linha-form">
            <div>
                <label>Valor (R$)</label>
                <input type="text" name="valor" value="<?= e((string)$dados['valor']) ?>" placeholder="Ex: 150,00" required>
            </div>
            <div>
                <label>Data</label>
                <input type="date" name="data_transacao" value="<?= e($dados['data_transacao']) ?>" required>
            </div>
        </div>

        <label>Categoria</label>
        <select name="categoria_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= e((string)$c['id']) ?>"
                    data-tipo="<?= e($c['tipo']) ?>"
                    <?= (string)$c['id'] === (string)$dados['categoria_id'] ? 'selected' : '' ?>>
                    <?= e($c['nome']) ?> (<?= $c['tipo']==='receita'?'receita':'despesa' ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Descrição (opcional)</label>
        <input type="text" name="descricao" value="<?= e($dados['descricao']) ?>" placeholder="Ex: Supermercado do mês">

        <button type="submit" class="btn"><?= $edicao ? 'Salvar alterações' : 'Adicionar lançamento' ?></button>
        <a href="/transacoes.php" class="btn secundario">Cancelar</a>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
