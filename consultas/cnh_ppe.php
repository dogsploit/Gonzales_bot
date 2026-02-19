<?php
header('Content-Type: application/json; charset=utf-8');

/* =====================================================
   1) VALIDAR CPF
===================================================== */
$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');

if (strlen($cpf) !== 11) {
    echo json_encode(['ok'=>false,'erro'=>'CPF inválido'], JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   2) CONFIG
===================================================== */
$url        = 'https://appbuscacheckonn.com/consultas/consultacpf.aspx';
$cookieFile = __DIR__ . '/cookies.txt';

/* =====================================================
   3) GET INICIAL (PEGA VIEWSTATE + COOKIES)
===================================================== */
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
]);

$htmlGet = curl_exec($ch);
curl_close($ch);

if (!$htmlGet) {
    echo json_encode(['ok'=>false,'erro'=>'Falha no GET inicial'], JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   4) EXTRAI VIEWSTATE
===================================================== */
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($htmlGet);
$xp = new DOMXPath($dom);

function inputValue($xp, $name) {
    return $xp->query("//input[@name='{$name}']")->item(0)?->getAttribute('value') ?? '';
}

$viewstate  = inputValue($xp, '__VIEWSTATE');
$eventvalid = inputValue($xp, '__EVENTVALIDATION');
$viewgen    = inputValue($xp, '__VIEWSTATEGENERATOR');

if (!$viewstate || !$eventvalid) {
    echo json_encode(['ok'=>false,'erro'=>'VIEWSTATE não encontrado'], JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   5) POST REAL (COM VIEWSTATE)
===================================================== */
$post = http_build_query([
    '__EVENTTARGET' => '',
    '__EVENTARGUMENT' => '',
    '__VIEWSTATE' => $viewstate,
    '__VIEWSTATEGENERATOR' => $viewgen,
    '__EVENTVALIDATION' => $eventvalid,
    'ctl00$ctl00$MainContent$ddlTipoConsulta' => 'Auto',
    'ctl00$ctl00$MainContent$tbCPF' => $cpf,
    'ctl00$ctl00$MainContent$btnConsultar' => 'Consultar'
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 40
]);

$html = curl_exec($ch);
curl_close($ch);

if (!$html || strlen($html) < 3000) {
    echo json_encode(['ok'=>false,'erro'=>'POST sem retorno válido'], JSON_PRETTY_PRINT);
    exit;
}

/* =====================================================
   6) PARSE FINAL (HTML COM DADOS)
===================================================== */
$dom->loadHTML($html);
$xp = new DOMXPath($dom);

function texto($xp, $q) {
    return trim($xp->query($q)?->item(0)?->textContent ?? '');
}

function label($xp, $l) {
    return texto($xp, "//label[@class='label3' and contains(text(),'$l')]/following-sibling::span[1]");
}

$nome = texto($xp, "//span[@class='label1' and contains(text(),'Nome')]/following-sibling::span[@class='text1']");
$sexo = label($xp, 'Sexo');
$nasc = label($xp, 'Data de nascimento');
$mae  = label($xp, 'Nome da mãe');
$rf   = label($xp, 'Situação na Receita Federal');

/* Telefones */
$telefones = [];
foreach ($xp->query("//a[starts-with(@href,'tel:')]") as $a) {
    $telefones[] = preg_replace('/\D/', '', $a->textContent);
}
$telefones = array_values(array_unique($telefones));

/* =====================================================
   7) JSON FINAL
===================================================== */
echo json_encode([
    'ok' => true,
    'cpf' => $cpf,
    'nome' => $nome,
    'sexo' => $sexo,
    'data_nascimento' => $nasc,
    'nome_mae' => $mae,
    'situacao_rf' => $rf,
    'telefones' => $telefones
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
