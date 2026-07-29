-- ==========================================================
-- Controle de Finanças Pessoal - Script de criação do banco
-- Cada usuário tem seus próprios dados (não é compartilhado).
-- Tabelas prefixadas com "financas_" porque este banco pode
-- ser compartilhado com outros projetos no futuro.
-- Importe este arquivo no phpMyAdmin (aba "Importar") depois
-- de criar um banco de dados vazio na Hostinger.
-- ==========================================================

CREATE TABLE IF NOT EXISTS financas_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financas_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(50) NOT NULL,
    tipo ENUM('receita','despesa','investimento') NOT NULL,
    cor VARCHAR(7) NOT NULL DEFAULT '#2a78d6',
    ordem INT NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES financas_usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financas_transacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    tipo ENUM('receita','despesa','investimento') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255) NULL,
    data_transacao DATE NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES financas_usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES financas_categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
