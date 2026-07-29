<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['familia_id']   = $usuario['familia_id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        header('Location: /index.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos.';
    }
}

$titulo_pagina = 'Entrar';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-caixa">
    <h1>Entrar</h1>
    <p class="subtitulo">Acesse as finanças da sua família</p>

    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="post">
        <label>E-mail</label>
        <input type="email" name="email" required autofocus>

        <label>Senha</label>
        <input type="password" name="senha" required>

        <button type="submit" class="btn" style="width:100%;">Entrar</button>
    </form>
    <p style="text-align:center; margin-top:1.2rem; font-size:0.9rem;">
        Ainda não tem conta? <a href="/auth/register.php">Criar conta</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
