<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha nome, e-mail e senha.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM financas_usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            $pdo->beginTransaction();

            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO financas_usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
            $stmt->execute([$nome, $email, $hash]);
            $usuario_id = $pdo->lastInsertId();

            $insereCat = $pdo->prepare('INSERT INTO financas_categorias (usuario_id, nome, tipo, cor, ordem) VALUES (?, ?, ?, ?, ?)');
            foreach (categorias_padrao() as $i => [$catNome, $catTipo]) {
                $insereCat->execute([$usuario_id, $catNome, $catTipo, PALETA_CATEGORIAS[$i % count(PALETA_CATEGORIAS)], $i]);
            }

            $pdo->commit();

            $_SESSION['usuario_id']  = $usuario_id;
            $_SESSION['usuario_nome'] = $nome;
            header('Location: /index.php');
            exit;
        }
    }
}

$titulo_pagina = 'Criar conta';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-caixa">
    <div class="marca"><span class="bola">M</span> Minhas Finanças</div>

    <div>
        <h1>Criar conta</h1>
        <p class="subtitulo">Comece a organizar suas finanças hoje.</p>
    </div>

    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="post">
        <label>Nome</label>
        <input type="text" name="nome" value="<?= e($_POST['nome'] ?? '') ?>" required>

        <label>E-mail</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label>Senha (mínimo 6 caracteres)</label>
        <input type="password" name="senha" required>

        <button type="submit" class="btn" style="width:100%; justify-content:center;">Criar conta</button>
    </form>
    <p style="text-align:center; font-size:14px; margin:0;">
        Já tem conta? <a href="/auth/login.php">Entrar</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
