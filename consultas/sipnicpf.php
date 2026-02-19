<?php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_URL = "https://api.gonzalesdev.shop/meuprojeto/sipni/api.php?cpf=";
$CONNECT_TIMEOUT = 5;
$TOTAL_TIMEOUT   = 20;

$TELEGRAM_LIMIT  = 4096;
$TELEGRAPH_TOKEN = '68cecec550328fd83935277b2f08341042396c527c5b4b061c02894fdbdb';

/* ================= VALID CPF ================= */
$cpfArg = isset($ARG) ? (string)$ARG : '';
$cpf = preg_replace('/\D+/', '', $cpfArg);

if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
    return "⚠️ <b>CPF inválido.</b>";
}

$cpfFmt = substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2);

/* ================= HELPERS ================= */
function esc($v) {
    if ($v === null || $v === '' || strtolower($v) === 'null') return 'Sem informação';
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function api_get($url, $ct, $tt) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $ct,
        CURLOPT_TIMEOUT        => $tt,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function idade($data) {
    if (!preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) return '';
    return (new DateTime("{$m[3]}-{$m[2]}-{$m[1]}"))->diff(new DateTime())->y . " anos";
}

/* ================= TELEGRAPH ================= */
function telegraphCreate($token, $title, $content) {
    $ch = curl_init("https://api.telegra.ph/createPage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'access_token' => $token,
            'title' => $title,
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'return_content' => false
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($res, true);
    return ($j['ok'] ?? false) ? $j['result']['url'] : null;
}

/* ================= REQUEST ================= */
$data = api_get($API_URL.$cpf, $CONNECT_TIMEOUT, $TOTAL_TIMEOUT);

if (!$data || empty($data['success']) || empty($data['cidadao'])) {
    return "⚠️ <b>Não foi possível consultar este CPF.</b>";
}

$c = $data['cidadao'];
$vacs = $data['vacinas'] ?? [];

/* ================= DADOS ================= */
$nome = esc($c['nome'] ?? '');
$sexo = esc($c['sexo'] ?? '');
$nasc = esc($c['dataNascimento'] ?? '');
$idadeTxt = idade($nasc);
if ($idadeTxt) $nasc .= " ({$idadeTxt})";

$cnsDef = esc($c['cns'] ?? '');
$cnsProv = !empty($c['cnsProvisorio']) ? implode(', ', $c['cnsProvisorio']) : 'Sem informação';

$mae = esc($c['nomeMae'] ?? '');
$pai = esc($c['nomePai'] ?? '');

if ($c['obito'] === true) {
    $dataObito = esc($c['dataObito'] ?? '');
    $obito = $dataObito !== 'Sem informação'
        ? "SIM ({$dataObito})"
        : "SIM";
} else {
    $obito = 'NÃO';
}

$natMun = esc($c['naturalidade']['municipio'] ?? '');
$natUf  = esc($c['naturalidade']['uf'] ?? '');

/* ================= ENDEREÇO ================= */
$e = $c['endereco'] ?? [];

/* ================= TELEFONE ================= */
$telTxt = "Sem informação";
if (!empty($c['telefone']['numero'])) {
    $telTxt = "CELULAR: +{$c['telefone']['ddi']} {$c['telefone']['numero']}";
}

/* ================= VACINAS (TELEGRAM) ================= */
$vacinasTxt = '';
foreach ($vacs as $v) {
    $vacinasTxt .= "° <b>{$v['nome']}</b>\n";
    $vacinasTxt .= "• Dose: {$v['dose']}\n";
    $vacinasTxt .= "• Data: {$v['dataAplicacao']}\n";
    $vacinasTxt .= "• Lote: {$v['lote']}\n";
    $vacinasTxt .= "• Fabricante: {$v['fabricante']}\n";
    $vacinasTxt .= "• Estabelecimento: {$v['estabelecimento']}\n";
    $vacinasTxt .= "• Profissional: {$v['profissional']}\n";
    if (!empty($v['campanha'])) {
        $vacinasTxt .= "• Campanha: {$v['campanha']}\n";
    }
    $vacinasTxt .= "\n";
}

if ($vacinasTxt === '') {
    $vacinasTxt = "Sem registros de vacinação.";
}

/* ================= TEXTO TELEGRAM ================= */
$txt = "🕵️ <b>CONSULTA CPF (SI-PNI)</b> 🕵️\n\n";
$txt .= "° <b>CPF:</b> <code>{$cpfFmt}</code>\n";
$txt .= "° <b>NOME:</b> <code>{$nome}</code>\n";
$txt .= "° <b>SEXO:</b> <code>{$sexo}</code>\n";
$txt .= "° <b>NASCIMENTO:</b> <code>{$nasc}</code>\n\n";

$txt .= "° <b>CNS DEFINITIVO:</b> <code>{$cnsDef}</code>\n";
$txt .= "° <b>CNS PROVISÓRIO:</b> <code>{$cnsProv}</code>\n\n";

$txt .= "° <b>MÃE:</b> <code>{$mae}</code>\n";
$txt .= "° <b>PAI:</b> <code>{$pai}</code>\n\n";

$txt .= "° <b>ÓBITO:</b> <code>{$obito}</code>\n\n";

$txt .= "° <b>NATURALIDADE:</b> <code>{$natMun} / {$natUf}</code>\n\n";

$txt .= "° <b>ENDEREÇO:</b>\n";
$txt .= "° LOGRADOURO: <code>".esc($e['logradouro'])."</code>\n";
$txt .= "° NÚMERO: <code>".esc($e['numero'])."</code>\n";
$txt .= "° BAIRRO: <code>".esc($e['bairro'])."</code>\n";
$txt .= "° MUNICÍPIO: <code>".esc($e['municipio']['nome'])."</code>\n";
$txt .= "° UF: <code>".esc($e['municipio']['uf'])."</code>\n";
$txt .= "° CEP: <code>".esc($e['cep'])."</code>\n\n";

$txt .= "° <b>TELEFONE:</b>\n\n<code>{$telTxt}</code>\n\n";
$txt .= "° <b>VACINAS / IMUNIZAÇÕES:</b>\n\n{$vacinasTxt}";

/* ================= TELEGRAPH SE PASSAR DO LIMITE ================= */
if (mb_strlen($txt, 'UTF-8') > 3900) {

    $content = [];
    $content[] = ['tag'=>'h3','children'=>['CONSULTA CPF (SI-PNI)']];
    $content[] = ['tag'=>'pre','children'=>[
        "CPF: {$cpfFmt}\n".
        "NOME: {$nome}\n".
        "SEXO: {$sexo}\n".
        "NASCIMENTO: {$nasc}\n\n".
        "CNS DEFINITIVO: {$cnsDef}\n".
        "CNS PROVISÓRIO: {$cnsProv}\n\n".
        "MÃE: {$mae}\n".
        "PAI: {$pai}\n\n".
        "ÓBITO: {$obito}\n\n".
        "NATURALIDADE: {$natMun} / {$natUf}\n\n".
        "ENDEREÇO:\n".
        "LOGRADOURO: {$e['logradouro']}\n".
        "NÚMERO: {$e['numero']}\n".
        "BAIRRO: {$e['bairro']}\n".
        "MUNICÍPIO: {$e['municipio']['nome']}\n".
        "UF: {$e['municipio']['uf']}\n".
        "CEP: {$e['cep']}\n\n".
        "TELEFONE:\n{$telTxt}\n\n".
        "VACINAS:\n"
    ]];

    foreach ($vacs as $v) {
        $content[] = ['tag'=>'pre','children'=>[
            "Vacina: {$v['nome']}\n".
            "Dose: {$v['dose']}\n".
            "Data: {$v['dataAplicacao']}\n".
            "Lote: {$v['lote']}\n".
            "Fabricante: {$v['fabricante']}\n".
            "Estabelecimento: {$v['estabelecimento']}\n".
            "Profissional: {$v['profissional']}\n".
            (!empty($v['campanha']) ? "Campanha: {$v['campanha']}\n" : "")
        ]];
    }

    $url = telegraphCreate($TELEGRAPH_TOKEN, "Consulta CPF - {$cpfFmt}", $content);

    if ($url) {
        // Armazena URL no contexto global para o bot.php criar botão inline
        $GLOBALS['telegraph_url'] = $url;
        $GLOBALS['telegraph_button_text'] = '📄 Ver Resultado Completo';
        
        return "✅ <b>Consulta concluída.</b>\n\n🔗 Clique no botão abaixo para visualizar o relatório completo.";
    }

    return "⚠️ Resultado muito extenso, mas não foi possível gerar o relatório.";
}

return $txt;