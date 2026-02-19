<?php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_URL = "https://meuvpsbr.shop/git_api/cpf.php?cpf=";
$CONNECT_TIMEOUT = 5;
$TOTAL_TIMEOUT   = 20;

$TELEGRAM_LIMIT  = 3900;
$TELEGRAPH_TOKEN = '68cecec550328fd83935277b2f08341042396c527c5b4b061c02894fdbdb';

/* ================= CPF ================= */
$cpfArg = $ARG ?? '';
$cpf = preg_replace('/\D+/', '', $cpfArg);

if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) {
    return "⚠️ <b>CPF inválido.</b>";
}

$cpfFmt = substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2);

/* ================= HELPERS ================= */
function esc($v) {
    if ($v === null || $v === '' || strtolower($v) === 'null') {
        return 'SEM INFORMAÇÃO';
    }
    return htmlspecialchars(trim((string)$v), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function limparTelefone($t) {
    $t = preg_replace('/[^\d() ]+/', '', $t);
    return preg_replace('/\s+/', ' ', trim($t));
}

function idade($data) {
    if (!preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) return '';
    return (new DateTime("{$m[3]}-{$m[2]}-{$m[1]}"))
        ->diff(new DateTime())->y . " anos";
}

function api_get($url, $ct, $tt) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $ct,
        CURLOPT_TIMEOUT        => $tt,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ? json_decode($r, true) : null;
}

/* ================= TELEGRAPH ================= */
function telegraphCreate($token, $title, $content) {
    $ch = curl_init("https://api.telegra.ph/createPage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'access_token'   => $token,
            'title'          => $title,
            'content'        => json_encode($content, JSON_UNESCAPED_UNICODE),
            'return_content' => false
        ]
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $j = json_decode($r, true);
    return ($j['ok'] ?? false) ? $j['result']['url'] : null;
}

/* ================= REQUEST ================= */
$data = api_get($API_URL.$cpf, $CONNECT_TIMEOUT, $TOTAL_TIMEOUT);

if (!$data || empty($data['success']) || empty($data['data']['dados'])) {
    return "⚠️ <b>Consulta indisponível.</b>";
}

$d = $data['data']['dados'];

/* ================= DADOS PRINCIPAIS ================= */
$nome  = esc($d['nome']);
$sexo  = esc($d['sexo']);
$nasc  = esc($d['data_nascimento']);
$idadeTxt = idade($nasc);
if ($idadeTxt) $nasc .= " ({$idadeTxt})";

$mae = esc($d['filiacao']['mae'] ?? '');
$pai = esc($d['filiacao']['pai'] ?? '');

$signo        = esc($d['signo'] ?? '');
$situacaoCpf = esc($d['situacao_cpf'] ?? '');
$dataCpf     = esc($d['data_status_cpf'] ?? '');
$rg           = esc($d['rg'] ?? '');
$titulo       = esc($d['titulo_eleitor'] ?? '');

$obito = 'NÃO';

/* ================= TELEFONES ================= */
$phones = [];
foreach (['telefones_preferenciais','telefones_alternativos'] as $grp) {
    foreach ($d[$grp] ?? [] as $t) {
        if (!empty($t['numero'])) {
            $phones[] = limparTelefone($t['numero']);
        }
    }
}
$phones = array_unique($phones);
$telTxt = $phones ? implode("\n\n", array_map('esc', $phones)) : esc('SEM INFORMAÇÃO');

/* ================= ENDEREÇOS ================= */
function montarEnderecos($lista) {
    if (empty($lista)) return '';
    $out = '';
    foreach ($lista as $e) {
        $out .= esc($e['endereco'] ?? '')."\n";
        if (!empty($e['complemento'])) $out .= esc($e['complemento'])."\n";
        $out .= esc($e['bairro'] ?? '')."\n";
        if (!empty($e['cep'])) $out .= "CEP: ".esc($e['cep'])."\n";
        $out .= esc($e['cidade_uf'] ?? '')."\n\n";
    }
    return trim($out)."\n";
}

$endPref = montarEnderecos($d['enderecos_preferenciais'] ?? []);
$endAlt  = montarEnderecos($d['enderecos_alternativos'] ?? []);
$endCom  = montarEnderecos($d['enderecos_comerciais'] ?? []);

/* ================= POSSÍVEIS FAMILIARES ================= */
$famTxt = '';
foreach ($d['possiveis_familiares'] ?? [] as $f) {
    $famTxt .= esc($f['nome'])."\n";
    $famTxt .= esc($f['documento'])."\n\n";
}
$famTxt = $famTxt ?: esc('SEM INFORMAÇÃO');

/* ================= PESSOAS RELACIONADAS ================= */
$relTxt = '';
foreach ($d['pessoas_relacionadas'] ?? [] as $p) {
    $relTxt .= esc($p['relacao'])."\n";
    $relTxt .= esc($p['nome'])."\n";
    $relTxt .= esc($p['cpf'])."\n";
    if (!empty($p['data_nascimento']) && $p['data_nascimento'] !== '-') {
        $relTxt .= esc($p['data_nascimento'])."\n";
    }
    $relTxt .= "\n";
}
$relTxt = $relTxt ?: esc('SEM INFORMAÇÃO');

/* ================= HISTÓRICO PROFISSIONAL ================= */
$jobTxt = '';
foreach ($d['historico_profissional'] ?? [] as $h) {
    $jobTxt .= esc($h['empregador'])."\n";
    $jobTxt .= esc($h['cargo'])."\n";
    $jobTxt .= esc($h['setor'])."\n";
    $jobTxt .= "CNPJ: ".esc($h['cnpj'])."\n\n";
}
$jobTxt = $jobTxt ?: esc('SEM INFORMAÇÃO');

/* ================= TEXTO FINAL ================= */
$txt  = "🕵️ <b>CONSULTA CPF</b> 🕵️\n\n";
$txt .= "° <b>CPF:</b> <code>{$cpfFmt}</code>\n";
$txt .= "° <b>Nome:</b> <code>{$nome}</code>\n";
$txt .= "° <b>Sexo:</b> <code>{$sexo}</code>\n";
$txt .= "° <b>Nascimento:</b> <code>{$nasc}</code>\n";
$txt .= "° <b>Signo:</b> <code>{$signo}</code>\n";
$txt .= "° <b>Situação CPF:</b> <code>{$situacaoCpf}</code>\n";
$txt .= "° <b>Data Situação:</b> <code>{$dataCpf}</code>\n";
$txt .= "° <b>RG:</b> <code>{$rg}</code>\n";
$txt .= "° <b>Título Eleitor:</b> <code>{$titulo}</code>\n\n";

$txt .= "° <b>Óbito:</b> <code>{$obito}</code>\n\n";

$txt .= "° <b>Mãe:</b> <code>{$mae}</code>\n";
$txt .= "° <b>Pai:</b> <code>{$pai}</code>\n\n";

$txt .= "° <b>TELEFONES</b>\n\n<code>{$telTxt}</code>\n\n";

$txt .= "° <b>ENDEREÇOS PREFERENCIAIS</b>\n\n<code>{$endPref}</code>\n\n";
if ($endAlt) $txt .= "° <b>ENDEREÇOS ALTERNATIVOS</b>\n\n<code>{$endAlt}</code>\n\n";
if ($endCom) $txt .= "° <b>ENDEREÇOS COMERCIAIS</b>\n\n<code>{$endCom}</code>\n\n";

$txt .= "° <b>POSSÍVEIS FAMILIARES</b>\n\n<code>{$famTxt}</code>\n\n";
$txt .= "° <b>PESSOAS RELACIONADAS</b>\n\n<code>{$relTxt}</code>\n\n";
$txt .= "° <b>HISTÓRICO PROFISSIONAL</b>\n\n<code>{$jobTxt}</code>";

/* ================= TELEGRAPH ================= */
if (mb_strlen($txt, 'UTF-8') > $TELEGRAM_LIMIT) {
    $url = telegraphCreate(
        $TELEGRAPH_TOKEN,
        "Consulta CPF {$cpfFmt}",
        [['tag'=>'pre','children'=>[$txt]]]
    );
    if ($url) {
        // Armazena URL no contexto global para o bot.php criar botão inline
        $GLOBALS['telegraph_url'] = $url;
        $GLOBALS['telegraph_button_text'] = '📄 Ver Resultado Completo';
        
        return "✅ <b>Consulta concluída.</b>\n\n🔗 Clique no botão abaixo para visualizar o relatório completo.";
    }
}

return $txt;