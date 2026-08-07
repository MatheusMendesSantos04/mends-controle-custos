<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$erro = '';

// Exclusão de reserva
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare('DELETE FROM financas_reservas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$_GET['excluir'], $usuario_id]);
    header('Location: /reservas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_reserva') {
        $nome = trim($_POST['nome'] ?? '');
        $saldo_inicial = str_replace(',', '.', $_POST['saldo_inicial'] ?? '0');
        $cartao_id = $_POST['cartao_id'] !== '' ? (int) $_POST['cartao_id'] : null;

        if ($nome === '') {
            $erro = 'Informe um nome para a reserva.';
        } elseif (!is_numeric($saldo_inicial) || (float) $saldo_inicial < 0) {
            $erro = 'Informe um saldo inicial válido.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO financas_reservas (usuario_id, nome, saldo, cartao_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$usuario_id, $nome, $saldo_inicial, $cartao_id]);
            header('Location: /reservas.php');
            exit;
        }
    }

    if ($acao === 'movimento') {
        $reserva_id = (int) ($_POST['reserva_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'deposito';
        $valor = str_replace(',', '.', $_POST['valor'] ?? '');
        $data_movimento = $_POST['data_movimento'] ?? date('Y-m-d');
        $descricao = trim($_POST['descricao'] ?? '');

        $stmt = $pdo->prepare('SELECT * FROM financas_reservas WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$reserva_id, $usuario_id]);
        $reserva = $stmt->fetch();

        if (!$reserva) {
            $erro = 'Reserva não encontrada.';
        } elseif (!is_numeric($valor) || (float) $valor <= 0) {
            $erro = 'Informe um valor válido.';
        } elseif ($tipo === 'retirada' && (float) $valor > (float) $reserva['saldo']) {
            $erro = 'Essa reserva não tem saldo suficiente para essa retirada.';
        } else {
            $pdo->beginTransaction();
            $novo_saldo = $tipo === 'deposito'
                ? (float) $reserva['saldo'] + (float) $valor
                : (float) $reserva['saldo'] - (float) $valor;

            $stmt = $pdo->prepare('UPDATE financas_reservas SET saldo = ? WHERE id = ?');
            $stmt->execute([$novo_saldo, $reserva_id]);

            $stmt = $pdo->prepare('
                INSERT INTO financas_reserva_movimentos (reserva_id, tipo, valor, data_movimento, descricao)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$reserva_id, $tipo, $valor, $data_movimento, $descricao]);
            $pdo->commit();
            header('Location: /reservas.php');
            exit;
        }
    }
}

$stmt = $pdo->prepare('
    SELECT r.*, c.nome AS cartao_nome
    FROM financas_reservas r
    LEFT JOIN financas_cartoes c ON c.id = r.cartao_id
    WHERE r.usuario_id = ?
    ORDER BY r.nome
');
$stmt->execute([$usuario_id]);
$reservas = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, nome FROM financas_cartoes WHERE usuario_id = ? ORDER BY nome');
$stmt->execute([$usuario_id]);
$cartoes = $stmt->fetchAll();

$stmt = $pdo->prepare('
    SELECT m.*, r.nome AS reserva_nome
    FROM financas_reserva_movimentos m
    JOIN financas_reservas r ON r.id = m.reserva_id
    WHERE r.usuario_id = ?
    ORDER BY m.data_movimento DESC, m.id DESC
    LIMIT 15
');
$stmt->execute([$usuario_id]);
$movimentos = $stmt->fetchAll();

$titulo_pagina = 'Reservas';
require __DIR__ . '/includes/header.php';
?>

<h1>Reservas</h1>

<?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

<div class="cartao">
    <?php if (!$reservas): ?>
        <p class="vazio">Nenhuma reserva cadastrada ainda.</p>
    <?php else: ?>
    <div class="grade-categorias">
        <?php foreach ($reservas as $r): ?>
            <div class="cartao-categoria" style="flex-direction:column; align-items:stretch; gap:6px; --cor-cat: var(--investir);">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span class="nome-cat"><?= e($r['nome']) ?></span>
                    <a href="?excluir=<?= e((string)$r['id']) ?>" class="excluir" onclick="return confirm('Excluir esta reserva?');">excluir</a>
                </div>
                <span class="valor-mono" style="font-size:18px; color:var(--investir);"><?= e(formatar_moeda((float)$r['saldo'])) ?></span>
                <?php if ($r['cartao_nome']): ?>
                    <span style="font-size:12px; color:var(--color-neutral-700);">Garante o limite de: <?= e($r['cartao_nome']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="painel-grade">
    <div class="cartao">
        <h2>Nova reserva</h2>
        <form method="post">
            <input type="hidden" name="acao" value="criar_reserva">
            <label>Nome</label>
            <input type="text" name="nome" placeholder="Ex: Caixinha, Reserva de emergência" required>
            <label>Saldo inicial (R$)</label>
            <input type="text" name="saldo_inicial" placeholder="Ex: 0,00" value="0,00">
            <label>Vincular a um cartão (opcional)</label>
            <select name="cartao_id">
                <option value="">Nenhum</option>
                <?php foreach ($cartoes as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>">Garante limite de: <?= e($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Criar reserva</button>
        </form>
    </div>

    <div class="cartao">
        <h2>Depositar / Retirar</h2>
        <?php if (!$reservas): ?>
            <p class="vazio">Crie uma reserva primeiro.</p>
        <?php else: ?>
        <form method="post">
            <input type="hidden" name="acao" value="movimento">
            <label>Reserva</label>
            <select name="reserva_id" required>
                <?php foreach ($reservas as $r): ?>
                    <option value="<?= e((string)$r['id']) ?>"><?= e($r['nome']) ?> (<?= e(formatar_moeda((float)$r['saldo'])) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="seletor-tipo" style="grid-template-columns:1fr 1fr;">
                <div class="opt-receita">
                    <input type="radio" name="tipo" id="tipo-deposito" value="deposito" checked>
                    <label for="tipo-deposito">Depositar</label>
                </div>
                <div class="opt-despesa">
                    <input type="radio" name="tipo" id="tipo-retirada" value="retirada">
                    <label for="tipo-retirada">Retirar</label>
                </div>
            </div>
            <div class="linha-form">
                <div>
                    <label>Valor (R$)</label>
                    <input type="text" name="valor" placeholder="Ex: 100,00" required>
                </div>
                <div>
                    <label>Data</label>
                    <input type="date" name="data_movimento" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <label>Descrição (opcional)</label>
            <input type="text" name="descricao" placeholder="Ex: Sobra do mês">
            <button type="submit" class="btn">Registrar</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($movimentos): ?>
<div class="cartao">
    <h2>Últimas movimentações</h2>
    <div class="tabela-scroll">
    <table class="razao">
        <thead>
            <tr><th>Data</th><th>Reserva</th><th>Descrição</th><th style="text-align:right;">Valor</th></tr>
        </thead>
        <tbody>
        <?php foreach ($movimentos as $m): ?>
            <tr>
                <td><?= formatar_data($m['data_movimento']) ?></td>
                <td><?= e($m['reserva_nome']) ?></td>
                <td><?= e($m['descricao']) ?: '<span style="color:var(--color-neutral-700);">—</span>' ?></td>
                <td style="text-align:right;" class="valor-mono <?= $m['tipo']==='deposito' ? 'valor-investimento' : 'valor-despesa' ?>">
                    <?= $m['tipo']==='deposito' ? '+' : '-' ?> <?= formatar_moeda((float)$m['valor']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
