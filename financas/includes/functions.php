<?php
/**
 * Funções auxiliares usadas em várias páginas do sistema.
 */

// Paleta categórica (ordem fixa — nunca embaralhar) usada para
// atribuir automaticamente uma cor a cada categoria nova do usuário.
const PALETA_CATEGORIAS = [
    'oklch(56% 0.17 15)',  // vermelho
    'oklch(66% 0.15 45)',  // laranja
    'oklch(74% 0.14 75)',  // âmbar
    'oklch(72% 0.13 105)', // amarelo-verde
    'oklch(62% 0.13 135)', // verde
    'oklch(64% 0.12 165)', // verde-água
    'oklch(62% 0.12 195)', // ciano
    'oklch(58% 0.13 225)', // azul-ciano
    'oklch(58% 0.14 255)', // azul
    'oklch(54% 0.14 285)', // violeta
    'oklch(56% 0.17 315)', // magenta
    'oklch(58% 0.16 345)', // rosa
];

// Cores fixas para os três tipos de lançamento (usadas nos cartões do painel)
const COR_RECEITA = 'oklch(56% 0.12 150)';
const COR_DESPESA = 'oklch(56% 0.17 27)';
const COR_INVESTIMENTO = 'oklch(52% 0.09 260)';

// Meta: percentual da receita que deve ser guardado/investido todo mês
const META_INVESTIMENTO_PCT = 20;

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

// Escolhe uma cor da paleta para uma categoria nova, evitando repetir
// uma cor já usada por outra categoria do mesmo tipo (só repete se as
// 8 cores da paleta já estiverem todas em uso nesse tipo).
function proxima_cor_categoria(PDO $pdo, int $usuario_id, string $tipo): string {
    $stmt = $pdo->prepare('SELECT cor FROM financas_categorias WHERE usuario_id = ? AND tipo = ?');
    $stmt->execute([$usuario_id, $tipo]);
    $usadas = array_column($stmt->fetchAll(), 'cor');

    foreach (PALETA_CATEGORIAS as $cor) {
        if (!in_array($cor, $usadas, true)) {
            return $cor;
        }
    }
    return PALETA_CATEGORIAS[count($usadas) % count(PALETA_CATEGORIAS)];
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

// Calcula os segmentos (dasharray/dashoffset) de um gráfico de rosca em SVG
// a partir de uma lista ['nome' => ..., 'cor' => ..., 'valor' => ...]
function segmentos_rosca(array $itens, float $raio = 70): array {
    $total = array_sum(array_column($itens, 'valor'));
    $circunferencia = 2 * M_PI * $raio;
    $offset = 0;
    $segmentos = [];
    foreach ($itens as $item) {
        $fracao = $total > 0 ? $item['valor'] / $total : 0;
        $comprimento = $fracao * $circunferencia;
        $segmentos[] = [
            'nome' => $item['nome'],
            'cor' => $item['cor'],
            'dasharray' => round($comprimento, 2) . ' ' . round($circunferencia - $comprimento, 2),
            'dashoffset' => -round($offset, 2),
            'pct' => round($fracao * 100),
        ];
        $offset += $comprimento;
    }
    return $segmentos;
}

// Variação percentual entre dois valores. Retorna null quando não há
// base de comparação (mês anterior sem nenhum valor).
function variacao_percentual(float $atual, float $anterior): ?float {
    if ($anterior <= 0) {
        return null;
    }
    return (($atual - $anterior) / $anterior) * 100;
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
