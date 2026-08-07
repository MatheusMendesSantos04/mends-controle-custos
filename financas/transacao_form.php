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
    'cartao_id' => '',
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
    $dados['cartao_id']       = $_POST['cartao_id'] ?? '';
    $dados['descricao']       = trim($_POST['descricao'] ?? '');
    $dados['data_transacao']  = $_POST['data_transacao'] ?? date('Y-m-d');
    $id_edicao                = $_POST['id'] ?? null;
    $parcelas                 = $dados['tipo'] === 'despesa' ? max(1, (int) ($_POST['parcelas'] ?? 1)) : 1;
    $cartao_id                = $dados['cartao_id'] !== '' ? (int) $dados['cartao_id'] : null;

    if (!is_numeric($dados['valor']) || (float)$dados['valor'] <= 0) {
        $erro = 'Informe um valor válido, maior que zero.';
    } elseif (!$dados['categoria_id']) {
        $erro = 'Selecione uma categoria.';
    } else {
        if ($id_edicao) {
            $stmt = $pdo->prepare("
                UPDATE financas_transacoes
                SET tipo=?, valor=?, categoria_id=?, cartao_id=?, descricao=?, data_transacao=?
                WHERE id=? AND usuario_id=?
            ");
            $stmt->execute([
                $dados['tipo'], $dados['valor'], $dados['categoria_id'], $cartao_id,
                $dados['descricao'], $dados['data_transacao'], $id_edicao, $usuario_id
            ]);
        } elseif ($parcelas <= 1) {
            $stmt = $pdo->prepare("
                INSERT INTO financas_transacoes (usuario_id, categoria_id, cartao_id, tipo, valor, descricao, data_transacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $usuario_id, $dados['categoria_id'], $cartao_id,
                $dados['tipo'], $dados['valor'], $dados['descricao'], $dados['data_transacao']
            ]);
        } else {
            // Compra parcelada: gera uma linha por parcela, distribuída nos meses certos
            $cartao = null;
            if ($cartao_id) {
                $stmt = $pdo->prepare('SELECT * FROM financas_cartoes WHERE id = ? AND usuario_id = ?');
                $stmt->execute([$cartao_id, $usuario_id]);
                $cartao = $stmt->fetch() ?: null;
            }

            $valor_total = round((float) $dados['valor'], 2);
            $valor_parcela = round($valor_total / $parcelas, 2);
            $ultima_parcela = round($valor_total - ($valor_parcela * ($parcelas - 1)), 2);
            $datas = calcular_datas_parcelas($dados['data_transacao'], $cartao, $parcelas);
            $grupo = gerar_grupo_parcela();

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO financas_transacoes
                    (usuario_id, categoria_id, cartao_id, grupo_parcela, numero_parcela, total_parcelas, tipo, valor, descricao, data_transacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            for ($i = 0; $i < $parcelas; $i++) {
                $valor_desta = $i === $parcelas - 1 ? $ultima_parcela : $valor_parcela;
                $stmt->execute([
                    $usuario_id, $dados['categoria_id'], $cartao_id, $grupo, $i + 1, $parcelas,
                    $dados['tipo'], $valor_desta, $dados['descricao'], $datas[$i]
                ]);
            }
            $pdo->commit();
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

$stmt = $pdo->prepare('SELECT id, nome FROM financas_cartoes WHERE usuario_id = ? ORDER BY nome');
$stmt->execute([$usuario_id]);
$cartoes = $stmt->fetchAll();

$titulo_pagina = $edicao ? 'Editar lançamento' : 'Novo lançamento';
require __DIR__ . '/includes/header.php';
?>

<h1><?= $edicao ? 'Editar lançamento' : 'Novo lançamento' ?></h1>

<div class="cartao" style="max-width:560px;">
    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>
    <?php if ($dados['total_parcelas'] ?? null): ?>
        <div class="alerta" style="background:var(--color-accent-100); color:var(--color-accent-800);">
            Esta é a parcela <?= (int)$dados['numero_parcela'] ?>/<?= (int)$dados['total_parcelas'] ?> de uma compra parcelada.
            Editar aqui altera só esta parcela.
        </div>
    <?php endif; ?>

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
                <label>Valor (R$)<?php if (!$edicao): ?> — total da compra<?php endif; ?></label>
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
            <p style="font-size:13px; color:var(--color-neutral-700);">Você ainda não tem categorias. <a href="/categorias.php">Crie uma primeiro</a>.</p>
        <?php endif; ?>

        <div id="bloco-cartao">
            <label>Cartão (opcional)</label>
            <select name="cartao_id">
                <option value="">Nenhum (dinheiro/pix/débito)</option>
                <?php foreach ($cartoes as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>" <?= (string)$c['id'] === (string)$dados['cartao_id'] ? 'selected' : '' ?>>
                        <?= e($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (!$edicao): ?>
            <label>Parcelar em quantas vezes?</label>
            <input type="number" name="parcelas" min="1" max="48" value="1">
            <?php endif; ?>
        </div>

        <label>Descrição (opcional)</label>
        <input type="text" name="descricao" value="<?= e($dados['descricao']) ?>" placeholder="Ex: Supermercado do mês">

        <div style="display:flex; gap:10px; margin-top:var(--space-2);">
            <button type="submit" class="btn" style="flex:1; justify-content:center;"><?= $edicao ? 'Salvar alterações' : 'Adicionar lançamento' ?></button>
            <a href="/transacoes.php" class="btn secundario" style="flex:1; justify-content:center;">Cancelar</a>
        </div>
    </form>
</div>

<script>
// Filtra as opções de categoria conforme o tipo escolhido, e só mostra
// cartão/parcelamento quando o tipo é despesa.
const radios = document.querySelectorAll('input[name="tipo"]');
const select = document.getElementById('select-categoria');
const blocoCartao = document.getElementById('bloco-cartao');

function filtrarCategorias() {
    const tipoAtual = document.querySelector('input[name="tipo"]:checked').value;
    select.querySelectorAll('optgroup').forEach(grupo => {
        grupo.hidden = grupo.dataset.tipo !== tipoAtual;
    });
    const selecionada = select.querySelector('option:checked');
    if (selecionada && selecionada.dataset.tipo && selecionada.dataset.tipo !== tipoAtual) {
        select.value = '';
    }
    blocoCartao.style.display = tipoAtual === 'despesa' ? 'block' : 'none';
}
radios.forEach(r => r.addEventListener('change', filtrarCategorias));
filtrarCategorias();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
