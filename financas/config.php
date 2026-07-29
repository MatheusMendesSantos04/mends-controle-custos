<?php
/**
 * Configuração da conexão com o banco de dados.
 *
 * IMPORTANTE: preencha os 4 valores abaixo com os dados do banco
 * que você criar no painel da Hostinger (hPanel > Bancos de Dados MySQL).
 */

define('DB_HOST', 'localhost');       // normalmente 'localhost' na Hostinger
define('DB_NAME', 'u000000000_financas'); // nome do banco criado no hPanel
define('DB_USER', 'u000000000_usuario');  // usuário do banco criado no hPanel
define('DB_PASS', 'sua_senha_aqui');      // senha do banco

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

// Inicia a sessão em todas as páginas que incluírem este arquivo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
