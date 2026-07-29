<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$edicao = false;

$dados = [
    'id' => null,
    'tipo' => 'despesa',
    'valor' => '',
    'categoria_id' => '',
    'descricao' => '',
    'data_transacao' => date('Y-m-d'),
];

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM financas_transacoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$_GET['id'], $usuario_id]);
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
                UPDATE financas_transacoes
                SET tipo=?, valor=?, categoria_id=?, descricao=?, data_transacao=?
                WHERE id=? AND usuario_id=?
            ");
            $stmt->execute([
                $dados['tipo'], $dados['valor'], $dados['categoria_id'],
                $dados['descricao'], $dados['data_transacao'], $id_edicao, $usuario_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO financas_transacoes (usuario_id, categoria_id, tipo, valor, descricao, data_transacao)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario_id, $dados['categoria_id'],
                $dados['tipo'], $dados['valor'], $dados['descricao'], $dados['data_transacao']
            ]);
        }
        header('Location: /transacoes.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT id, nome, tipo FROM financas_categorias WHERE usuario_id = ? ORDER BY tipo, ordem, nome');
$stmt->execute([$usuario_id]);
$todas_categorias = $stmt->fetchAll();
$categorias_por_tipo = ['receita' => [], 'despesa' => [], 'investimento' => []];
foreach ($todas_categorias as $c) {
    $categorias_por_tipo[$c['tipo']][] = $c;
}

$titulo_pagina = $edicao ? 'Editar lançamento' : 'Novo lançamento';
require __DIR__ . '/includes/header.php';
?>

<h1><?= $edicao ? 'Editar lançamento' : 'Novo lançamento' ?></h1>

<div class="cartao" style="max-width:520px;">
    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="post" id="form-transacao">
        <?php if ($dados['id']): ?><input type="hidden" name="id" value="<?= e((string)$dados['id']) ?>"><?php endif; ?>

        <label>Tipo</label>
        <div class="seletor-tipo">
            <div class="opt-receita">
                <input type="radio" name="tipo" id="tipo-receita" value="receita" <?= $dados['tipo']==='receita' ? 'checked' : '' ?>>
                <label for="tipo-receita">Receita</label>
            </div>
            <div class="opt-despesa">
                <input type="radio" name="tipo" id="tipo-despesa" value="despesa" <?= $dados['tipo']==='despesa' ? 'checked' : '' ?>>
                <label for="tipo-despesa">Despesa</label>
            </div>
            <div class="opt-investimento">
                <input type="radio" name="tipo" id="tipo-investimento" value="investimento" <?= $dados['tipo']==='investimento' ? 'checked' : '' ?>>
                <label for="tipo-investimento">Investir</label>
            </div>
        </div>

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
        <select name="categoria_id" id="select-categoria" required>
            <option value="">Selecione...</option>
            <?php foreach (['receita', 'despesa', 'investimento'] as $t): ?>
                <optgroup label="<?= e(rotulo_tipo($t)) ?>" data-tipo="<?= e($t) ?>">
                    <?php foreach ($categorias_por_tipo[$t] as $c): ?>
                        <option value="<?= e((string)$c['id']) ?>" data-tipo="<?= e($t) ?>"
                            <?= (string)$c['id'] === (string)$dados['categoria_id'] ? 'selected' : '' ?>>
                            <?= e($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <?php if (!$todas_categorias): ?>
            <p style="font-size:0.85rem; color:var(--text-muted);">Você ainda não tem categorias. <a href="/categorias.php">Crie uma primeiro</a>.</p>
        <?php endif; ?>

        <label>Descrição (opcional)</label>
        <input type="text" name="descricao" value="<?= e($dados['descricao']) ?>" placeholder="Ex: Supermercado do mês">

        <button type="submit" class="btn"><?= $edicao ? 'Salvar alterações' : 'Adicionar lançamento' ?></button>
        <a href="/transacoes.php" class="btn secundario">Cancelar</a>
    </form>
</div>

<script>
// Filtra as opções de categoria conforme o tipo escolhido
const radios = document.querySelectorAll('input[name="tipo"]');
const select = document.getElementById('select-categoria');

function filtrarCategorias() {
    const tipoAtual = document.querySelector('input[name="tipo"]:checked').value;
    select.querySelectorAll('optgroup').forEach(grupo => {
        grupo.hidden = grupo.dataset.tipo !== tipoAtual;
    });
    const selecionada = select.querySelector('option:checked');
    if (selecionada && selecionada.dataset.tipo && selecionada.dataset.tipo !== tipoAtual) {
        select.value = '';
    }
}
radios.forEach(r => r.addEventListener('change', filtrarCategorias));
filtrarCategorias();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
