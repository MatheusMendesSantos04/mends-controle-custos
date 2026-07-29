<?php
/**
 * Funções auxiliares usadas em várias páginas do sistema.
 */

// Paleta categórica validada (ordem fixa — nunca embaralhar) usada para
// atribuir automaticamente uma cor a cada categoria nova do usuário.
const PALETA_CATEGORIAS = [
    '#2a78d6', // azul
    '#eb6834', // laranja
    '#1baf7a', // água
    '#eda100', // amarelo
    '#e87ba4', // magenta
    '#008300', // verde
    '#4a3aa7', // violeta
    '#e34948', // vermelho
];

// Cores fixas para os três tipos de lançamento (usadas nos cartões do painel)
const COR_RECEITA = '#1baf7a';
const COR_DESPESA = '#e34948';
const COR_INVESTIMENTO = '#2a78d6';

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

// Escapa texto para exibir com segurança dentro de HTML
function e(?string $texto): string {
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// Escolhe a próxima cor da paleta para uma categoria nova, ciclando
// conforme a quantidade de categorias que o usuário já tem.
function proxima_cor_categoria(PDO $pdo, int $usuario_id): string {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM financas_categorias WHERE usuario_id = ?');
    $stmt->execute([$usuario_id]);
    $total = (int) $stmt->fetch()['total'];
    return PALETA_CATEGORIAS[$total % count(PALETA_CATEGORIAS)];
}

// Rótulo amigável e cor para cada tipo de lançamento
function rotulo_tipo(string $tipo): string {
    return match ($tipo) {
        'receita' => 'Receita',
        'despesa' => 'Despesa',
        'investimento' => 'Investir',
        default => $tipo,
    };
}

function cor_tipo(string $tipo): string {
    return match ($tipo) {
        'receita' => COR_RECEITA,
        'despesa' => COR_DESPESA,
        'investimento' => COR_INVESTIMENTO,
        default => '#898781',
    };
}

// Categorias padrão sugeridas para um usuário novo começar
function categorias_padrao(): array {
    return [
        ['Salário', 'receita'],
        ['Freelance / Extra', 'receita'],
        ['Outras receitas', 'receita'],
        ['Alimentação', 'despesa'],
        ['Moradia', 'despesa'],
        ['Transporte', 'despesa'],
        ['Saúde', 'despesa'],
        ['Lazer', 'despesa'],
        ['Educação', 'despesa'],
        ['Assinaturas', 'despesa'],
        ['Outros gastos', 'despesa'],
        ['Reserva de emergência', 'investimento'],
        ['Renda fixa', 'investimento'],
        ['Renda variável', 'investimento'],
    ];
}
