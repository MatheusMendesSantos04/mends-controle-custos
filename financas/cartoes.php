<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
exigir_login();

$usuario_id = $_SESSION['usuario_id'];
$erro = '';

// Exclusão
if (isset($_GET['excluir'])) {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM financas_transacoes WHERE cartao_id = ? AND usuario_id = ?');
    $stmt->execute([$_GET['excluir'], $usuario_id]);
    if ($stmt->fetch()['total'] > 0) {
        $erro = 'Não é possível excluir um cartão que já tem lançamentos.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM financas_cartoes WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$_GET['excluir'], $usuario_id]);
        header('Location: /cartoes.php');
        exit;
    }
}

// Criação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $dono = trim($_POST['dono'] ?? '');
    $limite = str_replace(',', '.', $_POST['limite'] ?? '');
    $dia_fechamento = (int) ($_POST['dia_fechamento'] ?? 0);
    $dia_vencimento = (int) ($_POST['dia_vencimento'] ?? 0);

    if ($nome === '' || $dono === '') {
        $erro = 'Preencha o nome e o dono do cartão.';
    } elseif (!is_numeric($limite) || (float) $limite <= 0) {
        $erro = 'Informe um limite válido.';
    } elseif ($dia_fechamento < 1 || $dia_fechamento > 31 || $dia_vencimento < 1 || $dia_vencimento > 31) {
        $erro = 'Dia de fechamento e vencimento devem estar entre 1 e 31.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO financas_cartoes (usuario_id, nome, dono, limite, dia_fechamento, dia_vencimento)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$usuario_id, $nome, $dono, $limite, $dia_fechamento, $dia_vencimento]);
        header('Location: /cartoes.php');
        exit;
    }
}

// Lista de cartões com o total comprometido (parcelas de despesa não pagas ainda, mês atual em diante)
$stmt = $pdo->prepare('
    SELECT c.*, COALESCE(SUM(t.valor), 0) AS comprometido
    FROM financas_cartoes c
    LEFT JOIN financas_transacoes t ON t.cartao_id = c.id AND t.tipo = "despesa" AND t.data_transacao >= DATE_FORMAT(CURDATE(), "%Y-%m-01")
    WHERE c.usuario_id = ?
    GROUP BY c.id
    ORDER BY c.nome
');
$stmt->execute([$usuario_id]);
$cartoes = $stmt->fetchAll();

$titulo_pagina = 'Cartões';
require __DIR__ . '/includes/header.php';
?>

<h1>Cartões</h1>

<?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

<div class="cartao" style="max-width:520px;">
    <h2>Novo cartão</h2>
    <form method="post">
        <div class="linha-form">
            <div>
                <label>Nome</label>
                <input type="text" name="nome" placeholder="Ex: Meu cartão" required>
            </div>
            <div>
                <label>De quem é</label>
                <input type="text" name="dono" placeholder="Ex: Você, Pai..." required>
            </div>
        </div>
        <label>Limite (R$)</label>
        <input type="text" name="limite" placeholder="Ex: 1500,00" required>
        <div class="linha-form">
            <div>
                <label>Dia de fechamento</label>
                <input type="number" name="dia_fechamento" min="1" max="31" placeholder="Ex: 20" required>
            </div>
            <div>
                <label>Dia de vencimento</label>
                <input type="number" name="dia_vencimento" min="1" max="31" placeholder="Ex: 28" required>
            </div>
        </div>
        <button type="submit" class="btn">Adicionar cartão</button>
    </form>
</div>

<div class="cartao">
    <?php if (!$cartoes): ?>
        <p class="vazio">Nenhum cartão cadastrado ainda.</p>
    <?php else: ?>
    <div class="grade-categorias">
        <?php foreach ($cartoes as $c): ?>
            <?php
                $percentual_uso = $c['limite'] > 0 ? min(100, ((float)$c['comprometido'] / (float)$c['limite']) * 100) : 0;
            ?>
            <div class="cartao-categoria" style="flex-direction:column; align-items:stretch; gap:8px; --cor-cat: var(--color-accent);">
                <div style="display:flex; align-items:center; justify-content:space-between;">
                    <span class="nome-cat"><?= e($c['nome']) ?></span>
                    <a href="?excluir=<?= e((string)$c['id']) ?>" class="excluir" onclick="return confirm('Excluir este cartão?');">excluir</a>
                </div>
                <span style="font-size:12px; color:var(--color-neutral-700);">
                    <?= e($c['dono']) ?> · fecha dia <?= e((string)$c['dia_fechamento']) ?>, vence dia <?= e((string)$c['dia_vencimento']) ?>
                </span>
                <div class="barra-progresso">
                    <div class="barra-progresso-preenchida" style="width:<?= round($percentual_uso) ?>%; background:<?= $percentual_uso >= 90 ? 'var(--despesa)' : 'var(--color-accent)' ?>;"></div>
                </div>
                <span style="font-size:12px; color:var(--color-neutral-700);">
                    <?= e(formatar_moeda((float)$c['comprometido'])) ?> de <?= e(formatar_moeda((float)$c['limite'])) ?> usados este mês
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
