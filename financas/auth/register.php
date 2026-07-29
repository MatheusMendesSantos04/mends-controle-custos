<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../includes/functions.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $modo  = $_POST['modo'] ?? 'criar'; // 'criar' uma família nova ou 'entrar' com código
    $nome_familia   = trim($_POST['nome_familia'] ?? '');
    $codigo_convite = strtoupper(trim($_POST['codigo_convite'] ?? ''));

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha nome, e-mail e senha.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha precisa ter pelo menos 6 caracteres.';
    } else {
        // Verifica se o e-mail já existe
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com esse e-mail.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($modo === 'criar') {
                    if ($nome_familia === '') {
                        throw new Exception('Informe um nome para a família.');
                    }
                    // Gera um código de convite único
                    do {
                        $codigo = gerar_codigo_convite();
                        $verifica = $pdo->prepare('SELECT id FROM familias WHERE codigo_convite = ?');
                        $verifica->execute([$codigo]);
                    } while ($verifica->fetch());

                    $stmt = $pdo->prepare('INSERT INTO familias (nome, codigo_convite) VALUES (?, ?)');
                    $stmt->execute([$nome_familia, $codigo]);
                    $familia_id = $pdo->lastInsertId();

                    // Categorias padrão para começar
                    $padrao = [
                        ['Salário', 'receita'], ['Outras receitas', 'receita'],
                        ['Alimentação', 'despesa'], ['Moradia', 'despesa'],
                        ['Transporte', 'despesa'], ['Saúde', 'despesa'],
                        ['Lazer', 'despesa'], ['Outros gastos', 'despesa'],
                    ];
                    $insereCat = $pdo->prepare('INSERT INTO categorias (familia_id, nome, tipo) VALUES (?, ?, ?)');
                    foreach ($padrao as [$catNome, $catTipo]) {
                        $insereCat->execute([$familia_id, $catNome, $catTipo]);
                    }
                } else {
                    if ($codigo_convite === '') {
                        throw new Exception('Informe o código de convite da família.');
                    }
                    $busca = $pdo->prepare('SELECT id FROM familias WHERE codigo_convite = ?');
                    $busca->execute([$codigo_convite]);
                    $familia = $busca->fetch();
                    if (!$familia) {
                        throw new Exception('Código de convite inválido.');
                    }
                    $familia_id = $familia['id'];
                }

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (familia_id, nome, email, senha_hash) VALUES (?, ?, ?, ?)');
                $stmt->execute([$familia_id, $nome, $email, $hash]);
                $usuario_id = $pdo->lastInsertId();

                $pdo->commit();

                $_SESSION['usuario_id']  = $usuario_id;
                $_SESSION['familia_id']  = $familia_id;
                $_SESSION['usuario_nome'] = $nome;
                header('Location: /index.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = $e->getMessage();
            }
        }
    }
}

$titulo_pagina = 'Criar conta';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-caixa">
    <h1>Criar conta</h1>
    <p class="subtitulo">Comece uma nova família ou entre em uma já existente</p>

    <?php if ($erro): ?><div class="alerta erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="post">
        <label>Seu nome</label>
        <input type="text" name="nome" value="<?= e($_POST['nome'] ?? '') ?>" required>

        <label>E-mail</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label>Senha (mínimo 6 caracteres)</label>
        <input type="password" name="senha" required>

        <label>O que você quer fazer?</label>
        <select name="modo" id="modo" onchange="document.getElementById('bloco-criar').style.display = this.value==='criar' ? 'block':'none'; document.getElementById('bloco-entrar').style.display = this.value==='entrar' ? 'block':'none';">
            <option value="criar">Criar uma nova família</option>
            <option value="entrar">Entrar em uma família existente</option>
        </select>

        <div id="bloco-criar">
            <label>Nome da família (ex: "Família Silva")</label>
            <input type="text" name="nome_familia" value="<?= e($_POST['nome_familia'] ?? '') ?>">
        </div>

        <div id="bloco-entrar" style="display:none;">
            <label>Código de convite da família</label>
            <input type="text" name="codigo_convite" placeholder="Ex: AB12CD" value="<?= e($_POST['codigo_convite'] ?? '') ?>">
        </div>

        <button type="submit" class="btn" style="width:100%;">Criar conta</button>
    </form>
    <p style="text-align:center; margin-top:1.2rem; font-size:0.9rem;">
        Já tem conta? <a href="/auth/login.php">Entrar</a>
    </p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
