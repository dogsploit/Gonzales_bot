<?php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= VALIDAÇÃO ================= */

if (!isset($ARG) || trim($ARG) === '') {
    return "⚠️ <b>Uso incorreto.</b>\n\nExemplo:\n<code>/telefone 11987654321</code>";
}

/* ================= NORMALIZA TELEFONE ================= */

function normalizarTelefone($input): array {
    // Remove TUDO que não é número
    $n = preg_replace('/\D+/', '', trim($input));
    
    // Valida tamanho mínimo e máximo
    if (strlen($n) < 8 || strlen($n) > 14) {
        return [];
    }
    
    $list = [];
    
    // Remove código do país se tiver (55)
    if (strlen($n) >= 12 && substr($n, 0, 2) === '55') {
        $n = substr($n, 2);
    }
    
    // Adiciona formato original se válido
    if (strlen($n) >= 8 && strlen($n) <= 11) {
        $list[] = $n;
    }
    
    // Se tem 10 dígitos (DDD + 8), adiciona com 9
    if (strlen($n) === 10) {
        $ddd = substr($n, 0, 2);
        $numero = substr($n, 2);
        $list[] = $ddd . '9' . $numero;
    }
    
    // Se tem 11 dígitos (DDD + 9 + 8), adiciona sem 9
    if (strlen($n) === 11 && substr($n, 2, 1) === '9') {
        $ddd = substr($n, 0, 2);
        $numero = substr($n, 3);
        $list[] = $ddd . $numero;
    }
    
    // Remove duplicados e retorna
    return array_values(array_unique($list));
}

$formatos = normalizarTelefone($ARG);

// Valida se conseguiu gerar algum formato válido
if (empty($formatos)) {
    return "⚠️ <b>Telefone inválido!</b>\n\n"
         . "O número informado não é válido.\n\n"
         . "<b>Formato aceito:</b>\n"
         . "• <code>11987654321</code>\n"
         . "• <code>(11) 98765-4321</code>\n"
         . "• <code>11 98765-4321</code>\n"
         . "• <code>5511987654321</code>";
}

function esc($v): string {
    if ($v === null || $v === '') return 'SEM INFORMAÇÃO';
    return htmlspecialchars(trim((string)$v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function brDate($v): string {
    if (!$v) return 'SEM INFORMAÇÃO';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
        return "{$m[3]}/{$m[2]}/{$m[1]}";
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
        return $v;
    }

    return 'SEM INFORMAÇÃO';
}

function httpJson($url, $timeout = 15): ?array {
    $start = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_ENCODING => 'gzip, deflate',
    ]);
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $elapsed = round(microtime(true) - $start, 2);
    curl_close($ch);
    
    // Log para debug (apenas em desenvolvimento)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("API: $url | Code: $httpCode | Time: {$elapsed}s | Error: $error");
    }

    if (!$res || $httpCode !== 200) {
        return null;
    }

    $json = json_decode($res, true);
    return is_array($json) ? $json : null;
}

/* ================= APIS ================= */

$API1 = "https://vps-gonzales.duckdns.org/apis/api_credilink.php?telefone=";
$API2 = "https://meuvpsbr.shop/credilink/api.php?phone=";

/* ================= PROCESSAMENTO ================= */

$formatos = normalizarTelefone($ARG);

// Prioriza formato celular (11 dígitos) para tentar primeiro
$formatosPriorizados = [];
$formatosSecundarios = [];

foreach ($formatos as $f) {
    if (strlen($f) === 11) {
        $formatosPriorizados[] = $f;
    } else {
        $formatosSecundarios[] = $f;
    }
}

$formatosOrdenados = array_merge($formatosPriorizados, $formatosSecundarios);

/* ====== API 1 (VPS - RÁPIDA) ====== */
foreach ($formatosOrdenados as $tel) {
    $data1 = httpJson($API1 . urlencode($tel), 8);

    if (!empty($data1['dados_cadastrais']['NOME'])) {
        $dc = $data1['dados_cadastrais'];

        $txt  = "🕵️ <b>CONSULTA DE TELEFONE</b> 🕵️\n\n";
        $txt .= "🔎 <b>Telefone:</b> <code>{$tel}</code>\n\n";
        $txt .= "• <b>Nome:</b> <code>".esc($dc['NOME'])."</code>\n";

        if (!empty($dc['CPF'])) {
            $txt .= "• <b>CPF:</b> <code>".esc($dc['CPF'])."</code>\n";
        }

        if (!empty($dc['DT_NASCIMENTO'])) {
            $txt .= "• <b>Nascimento:</b> <code>".brDate($dc['DT_NASCIMENTO'])."</code>\n";
        }

        if (!empty($dc['NOME_MAE'])) {
            $txt .= "• <b>Mãe:</b> <code>".esc($dc['NOME_MAE'])."</code>\n";
        }

        return $txt;
    }
}

/* ====== API 2 (LOCAL - FALLBACK COM MAIS DADOS) ====== */

// Usa o melhor formato disponível (prioriza celular)
$telFinal = $formatosOrdenados[0] ?? '';

if ($telFinal === '') {
    return "⚠️ <b>Telefone inválido!</b>";
}

// Timeout maior para API 2 (mais completa, pode demorar mais)
$data2 = httpJson($API2 . urlencode($telFinal), 20);

if (!empty($data2['dadosPrincipais'])) {

    $p = $data2['dadosPrincipais'];
    $t = $data2['telefoneConsultado'] ?? [];
    $v = $data2['veiculos'] ?? [];

    $txt  = "🕵️ <b>CONSULTA DE TELEFONE</b> 🕵️\n\n";
    $txt .= "🔎 <b>Telefone:</b> <code>".esc($t['number'] ?? $telFinal)."</code>\n\n";

    $txt .= "• <b>Nome:</b> <code>".esc($p['nome'])."</code>\n";
    $txt .= "• <b>CPF:</b> <code>".esc($p['cpf'])."</code>\n";
    $txt .= "• <b>Nascimento:</b> <code>".brDate($p['dataNascimento'])."</code>\n";
    $txt .= "• <b>Idade:</b> <code>".esc($p['idade'])." anos</code>\n";
    $txt .= "• <b>Sexo:</b> <code>".esc($p['genero'])."</code>\n";
    $txt .= "• <b>Signo:</b> <code>".esc($p['signo'])."</code>\n\n";
    $txt .= "• <b>Mãe:</b> <code>".esc($p['mae'])."</code>\n";
    $txt .= "• <b>Pai:</b> <code>".esc($p['pai'])."</code>\n\n";

    if (!empty($t) && !empty($t['address'])) {
        $txt .= "<b>° ENDEREÇO ASSOCIADO AO TELEFONE</b>\n\n";
        $txt .= "<code>";
        $txt .= esc($t['address'])."\n";
        $txt .= esc($t['neighborhood'])."\n";
        if (!empty($t['zipCode'])) {
            $txt .= "CEP: ".esc($t['zipCode'])."\n";
        }
        $txt .= esc($t['city'])."/".esc($t['regionAbreviation']);
        $txt .= "</code>\n\n";
    }

    if (!empty($v) && is_array($v)) {
        $txt .= "<b>° VEÍCULOS</b>\n\n";
        foreach ($v as $i => $car) {
            if (!is_array($car)) continue;
            
            $txt .= "<code>";
            $txt .= "Placa: ".esc($car['plate'] ?? '')."\n";
            $txt .= "Modelo: ".esc($car['brand'] ?? '')."\n";
            $txt .= "Ano: ".esc($car['yearModel'] ?? '')."\n";
            $txt .= "RENAVAM: ".esc($car['renavan'] ?? '')."\n";
            $txt .= "</code>\n\n";
        }
    }

    return $txt;
}

/* ================= TIMEOUT OU API FORA DO AR ================= */

if ($data2 === null) {
    return "⚠️ <b>Serviço temporariamente indisponível.</b>\n\n"
         . "A consulta está demorando mais que o esperado.\n"
         . "Por favor, tente novamente em alguns instantes.";
}

/* ================= NADA ENCONTRADO ================= */

return "⚠️ <b>Telefone não encontrado!</b>\n\n"
     . "O número informado não foi localizado em nossas bases de dados.";