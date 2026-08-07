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
            'valor' => $item['valor'],
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

// Calcula em qual mês cada parcela de uma compra cai, considerando o dia
// de fechamento da fatura do cartão (se houver cartão selecionado).
// Retorna um array de datas (Y-m-d) com $total_parcelas posições.
function calcular_datas_parcelas(string $data_compra, ?array $cartao, int $total_parcelas): array {
    $dt = new DateTime($data_compra);
    $dia_compra = (int) $dt->format('j');

    if ($cartao) {
        // Se a compra foi feita depois do fechamento, só entra na fatura seguinte.
        $offset_inicial = $dia_compra > (int) $cartao['dia_fechamento'] ? 2 : 1;
    } else {
        // Sem cartão (dinheiro/pix parcelado no boleto): a 1ª parcela é no mês da compra.
        $offset_inicial = 0;
    }

    $datas = [];
    for ($i = 0; $i < $total_parcelas; $i++) {
        $data_parcela = clone $dt;
        $data_parcela->modify('+' . ($offset_inicial + $i) . ' months');
        $datas[] = $data_parcela->format('Y-m-d');
    }
    return $datas;
}

// Gera um identificador único para agrupar as parcelas de uma mesma compra
function gerar_grupo_parcela(): string {
    return bin2hex(random_bytes(16));
}

// Converte uma lista de valores em pontos (x,y) dentro de uma área de
// desenho, usando uma escala compartilhada (mesmo $valor_maximo) — para
// que várias séries no mesmo gráfico fiquem na mesma escala vertical.
function escala_pontos(array $valores, float $largura, float $altura, float $valor_maximo): array {
    $n = count($valores);
    $pontos = [];
    foreach ($valores as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $largura : $largura / 2;
        $y = $valor_maximo > 0 ? $altura - (($v / $valor_maximo) * $altura) : $altura;
        $pontos[] = ['x' => round($x, 2), 'y' => round($y, 2)];
    }
    return $pontos;
}

// Monta o atributo "d" de um <path> SVG em curva suave que passa
// exatamente por cada ponto dado (spline Catmull-Rom convertida em
// curvas de Bézier cúbicas — ao contrário de uma suavização por ponto
// médio, o pico real dos dados nunca fica "cortado").
function path_suave(array $pontos): string {
    $n = count($pontos);
    if ($n < 2) {
        return '';
    }
    $d = "M {$pontos[0]['x']} {$pontos[0]['y']}";
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $pontos[max($i - 1, 0)];
        $p1 = $pontos[$i];
        $p2 = $pontos[$i + 1];
        $p3 = $pontos[min($i + 2, $n - 1)];

        $c1x = round($p1['x'] + ($p2['x'] - $p0['x']) / 6, 2);
        $c1y = round($p1['y'] + ($p2['y'] - $p0['y']) / 6, 2);
        $c2x = round($p2['x'] - ($p3['x'] - $p1['x']) / 6, 2);
        $c2y = round($p2['y'] - ($p3['y'] - $p1['y']) / 6, 2);

        $d .= " C {$c1x} {$c1y}, {$c2x} {$c2y}, {$p2['x']} {$p2['y']}";
    }
    return $d;
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
