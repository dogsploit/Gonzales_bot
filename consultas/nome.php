<?php
// consultas/nome.php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_ENDPOINT = 'https://vps-gonzales.duckdns.org/apis/api.php?nome=';
$TIMEOUT      = 25;
$MAX_RESULTS  = 120;
$TELEGRAM_MAX_LENGTH = 4096;
$TELEGRAPH_TOKEN = '68cecec550328fd83935277b2f08341042396c527c5b4b061c02894fdbdb';

/* ================= VALID INPUT ================= */
if (!isset($ARG) || trim((string)$ARG) === '') {
    return "⚠️ <b>Uso incorreto.</b>\n\nPor favor, informe o nome completo.\n<b>Exemplo:</b> <code>/nome JOAO SILVA</code>";
}

// Usa função global de validação e normalização
$validacao = validarNome($ARG);

if (!$validacao['valido']) {
    return "⚠️ <b>Nome inválido!</b>\n\n" . $validacao['erro'] . "\n\n"
         . "<b>Exemplos aceitos:</b>\n"
         . "• <code>João da Silva</code>\n"
         . "• <code>Maria José</code>\n"
         . "• <code>José Antônio</code>";
}

$nomeNormalizado = $validacao['nome'];
$nomeOriginal = $validacao['original'];

/* ================= HELPERS ================= */
function nome_http_get(string $url, int $timeout = 25): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // erro de rede/timeout
    if ($err) return null;

    // HTTP inválido
    if (!$res || $code < 200 || $code >= 300) return null;

    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

function esc(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nome_txt($v): string {
    $s = trim((string)$v);
    return ($s === '' || strtolower($s) === 'null') ? 'SEM INFORMAÇÃO' : esc($s);
}

function nome_cpf_fmt($doc): string {
    $d = preg_replace('/\D+/', '', (string)$doc);
    return (strlen($d) === 11)
        ? substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2)
        : 'SEM INFORMAÇÃO';
}

function nome_br_date($dt): string {
    $dt = trim((string)$dt);
    if ($dt === '' || strtolower($dt) === 'null') return 'SEM INFORMAÇÃO';
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dt)) return $dt;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dt, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return 'SEM INFORMAÇÃO';
}

function calcular_idade($dataNascimento): string {
    $data = trim((string)$dataNascimento);
    if ($data === '' || strtolower($data) === 'sem informação' || strtolower($data) === 'null') return 'SEM INFORMAÇÃO';

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
        $dia = (int)$m[1];
        $mes = (int)$m[2];
        $ano = (int)$m[3];
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) {
        $ano = (int)$m[1];
        $mes = (int)$m[2];
        $dia = (int)$m[3];
    } else {
        return 'SEM INFORMAÇÃO';
    }

    $hoje = new DateTime();
    $nasc = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    if (!$nasc) return 'SEM INFORMAÇÃO';

    $idade = $hoje->diff($nasc)->y;
    return "{$idade} anos";
}

/**
 * Normaliza texto para consulta:
 * - remove acentos/cedilha (João -> Joao)
 * - mantém letras, espaços e hífen
 * - reduz espaços repetidos
 */
function nome_normalize_query(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);

    // remove acentos (melhor compatibilidade com APIs que não aceitam diacríticos)
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false && $t !== '') $s = $t;

    // remove caracteres estranhos, mantém letras/números/espaço/hífen
    $s = preg_replace('/[^A-Za-z0-9 \-]/', '', $s);
    $s = preg_replace('/\s+/u', ' ', trim($s));

    return $s;
}

/* === Telegraph === */
function telegraphCreatePage(string $token, string $title, array $content): ?string {
    $apiUrl = 'https://api.telegra.ph/createPage';
    $postData = [
        'access_token'   => $token,
        'title'          => $title,
        'content'        => json_encode($content, JSON_UNESCAPED_UNICODE),
        'return_content' => false
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    return (isset($json['ok']) && $json['ok'] && isset($json['result']['url']))
        ? $json['result']['url']
        : null;
}

/* ================= CONSULTA ================= */

// Usa nome já normalizado pela função global
$url  = $API_ENDPOINT . urlencode($nomeNormalizado);
$data = nome_http_get($url, $TIMEOUT);

// se deu null, é falha de rede/api/timeout
if ($data === null) {
    return "⚠️ <b>Serviço temporariamente indisponível.</b>\n\nNão foi possível concluir a consulta neste momento.\nTente novamente em breve.";
}

// se retornou algo mas vazio/não-array => tratar como sem registros
if (!is_array($data) || empty($data)) {
    return "⚠️ <b>Nenhum registro localizado.</b>\n\n<i>Não encontramos resultados para o Nome que deseja consultar</i>\n<code>" . esc($nomeOriginal) . "</code>";
}

// filtra registros válidos
$registros = array_slice(array_values(array_filter($data, 'is_array')), 0, $MAX_RESULTS);
$totalResultados = count($registros);

if ($totalResultados === 0) {
    return "⚠️ <b>Nome não encontrado!</b>\n\n";
}

/* ================= MONTAR TEXTO (TELEGRAM) ================= */
$out = [];
$out[] = "🕵️ <b>CONSULTA DE NOME</b> 🕵️\n";

foreach ($registros as $i => $p) {
    $nome  = nome_txt($p['nome'] ?? '');
    $cpf   = nome_cpf_fmt($p['cpf'] ?? '');
    $sexo  = nome_txt($p['sexo'] ?? '');
    $nasc  = nome_br_date($p['data_nasc'] ?? '');
    $idade = calcular_idade($p['data_nasc'] ?? '');

    $out[] = "<b>• RESULTADO " . ($i + 1) . "</b\n\n>";
    $out[] = "\n<b>Nome:</b> <code>{$nome}</code>";
    $out[] = "<b>CPF:</b> <code>{$cpf}</code>";
    $out[] = "<b>Sexo:</b> <code>{$sexo}</code>";
    $out[] = "<b>Nascimento:</b> <code>{$nasc}</code>";
    $out[] = "<b>Idade:</b> <code>{$idade}</code>\n";
}

$txt = implode("\n", $out);

/* ================= TELEGRAPH (RESULTADO LONGO) ================= */
if (mb_strlen($txt, 'UTF-8') > 3900) { // melhor margem do que 4096
    $content = [];
    
    // 🎨 CABEÇALHO ESTILIZADO
    $content[] = [
        'tag' => 'h3',
        'children' => ['🕵️ CONSULTA DE NOME - RELATÓRIO COMPLETO']
    ];
    
    // 📊 INFO GERAL
    $content[] = [
        'tag' => 'blockquote',
        'children' => [
            "📝 Pesquisa: {$nomeOriginal}\n" .
            "📌 Total de resultados: {$totalResultados}\n" .
            "📅 Data: " . date('d/m/Y H:i:s')
        ]
    ];
    
    $content[] = ['tag' => 'hr'];

    // 📋 RESULTADOS ESTILIZADOS
    foreach ($registros as $i => $p) {
        $nome  = nome_txt($p['nome'] ?? '');
        $cpf   = nome_cpf_fmt($p['cpf'] ?? '');
        $sexo  = nome_txt($p['sexo'] ?? '');
        $nasc  = nome_br_date($p['data_nasc'] ?? '');
        $idade = calcular_idade($p['data_nasc'] ?? '');

        // Número do resultado
        $content[] = [
            'tag' => 'h4',
            'children' => ['👤 RESULTADO ' . ($i + 1)]
        ];

        // Card com informações (formato simples e limpo)
        $cardText = "Nome Completo: {$nome}\n";
        $cardText .= "CPF: {$cpf}\n";
        $cardText .= "Sexo: {$sexo}\n";
        $cardText .= "Data de Nascimento: {$nasc}\n";
        $cardText .= "Idade: {$idade}";

        $content[] = [
            'tag' => 'blockquote',
            'children' => [$cardText]
        ];
        
        // Separador visual entre resultados
        if ($i < $totalResultados - 1) {
            $content[] = ['tag' => 'hr'];
        }
    }
    
    // 🔒 RODAPÉ
    $content[] = ['tag' => 'hr'];
    $content[] = [
        'tag' => 'p',
        'children' => [
            ['tag' => 'em', 'children' => [
                '🔒 Relatório gerado automaticamente pelo sistema de consultas.\n' .
                '⚠️ Informações confidenciais - Uso restrito.'
            ]]
        ]
    ];

    $telegraphUrl = telegraphCreatePage($TELEGRAPH_TOKEN, '📋 Relatório - Consulta de Nome', $content);

    if ($telegraphUrl) {
        // Armazena URL no contexto global para o bot.php criar botão inline
        $GLOBALS['telegraph_url'] = $telegraphUrl;
        $GLOBALS['telegraph_button_text'] = '📄 Ver Resultado Completo';
        
        return "✅ <b>Consulta concluída.</b>\n\n"
             . "📌 <b>Total de resultados:</b> <code>{$totalResultados}</code>\n"
             . "📝 Clique no botão abaixo para visualizar o relatório completo.";
    }

    return "⚠️ <b>Resultado muito extenso.</b>\n\nNão foi possível gerar o relatório no Telegraph no momento.\nTente novamente em breve.";
}

return $txt;
?>