<?php
/**
 * Funções auxiliares usadas em várias páginas do sistema.
 */

// Bloqueia o acesso a páginas que exigem login
function exigir_login(): void {
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

// Formata um número para o padrão monetário brasileiro (R$ 1.234,56)
function formatar_moeda(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formata uma data do formato do banco (Y-m-d) para o formato brasileiro (d/m/Y)
function formatar_data(string $data): string {
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    return $dt ? $dt->format('d/m/Y') : $data;
}

// Gera um código de convite aleatório de 6 caracteres para a família
function gerar_codigo_convite(): string {
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem letras/números ambíguos
    $codigo = '';
    for ($i = 0; $i < 6; $i++) {
        $codigo .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $codigo;
}

// Escapa texto para exibir com segurança dentro de HTML
function e(?string $texto): string {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}
