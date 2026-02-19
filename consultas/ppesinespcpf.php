<?php
// consultas/cpf.php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_URL = 'https://meuvpsbr.shop/apis/cpf_ppe.php?cpf=';

/* ================= VALIDAÇÃO ================= */
if (!isset($ARG) || trim((string)$ARG) === '') {
    return "⚠️ <b>Informe um CPF para consulta!</b>";
}

$cpf = preg_replace('/\D+/', '', (string)$ARG);
if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
    return "⚠️ <b>CPF inválido!</b>";
}

/* ================= HELPERS ================= */
function safe($v): string {
    $v = trim((string)$v);
    if ($v === '' || strtolower($v) === 'null') return 'SEM INFORMAÇÃO';
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cpfFmt($v): string {
    $v = preg_replace('/\D+/', '', (string)$v);
    if (strlen($v) !== 11) return 'SEM INFORMAÇÃO';
    return substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.' . substr($v, 6, 3) . '-' . substr($v, 9, 2);
}

function idadeFmt($dataBr): string {
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataBr, $m)) return 'SEM INFORMAÇÃO';
    try {
        $nasc = new DateTime("{$m[3]}-{$m[2]}-{$m[1]}");
        $hoje = new DateTime('today');
        return $nasc->diff($hoje)->y . ' anos';
    } catch (Throwable $e) {
        return 'SEM INFORMAÇÃO';
    }
}

function diasParaAniversario($dataBr): string {
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataBr, $m)) return '';
    try {
        $dia = (int)$m[1];
        $mes = (int)$m[2];
        $tz = new DateTimeZone('America/Sao_Paulo');
        $hoje = new DateTime('now', $tz);
        $anoAtual = (int)$hoje->format('Y');

        $prox = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $anoAtual, $mes, $dia), $tz);
        if (!$prox) return '';

        if ($prox < $hoje) {
            $prox = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $anoAtual + 1, $mes, $dia), $tz);
        }

        $dias = (int)$hoje->diff($prox)->days;
        return $dias === 0 ? "🎉 É HOJE!" : "🎂 Faltam {$dias} dias para o aniversário";
    } catch (Throwable $e) {
        return '';
    }
}

function httpJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    if (!$r) return null;
    $j = json_decode($r, true);
    return is_array($j) ? $j : null;
}

/* ================= CONSULTA ================= */
$data = httpJson($API_URL . urlencode($cpf));

if (!$data || ($data['status'] ?? '') !== 'ok' || empty($data['dados'])) {
    return "⚠️ <b>CPF não encontrado na base de dados.</b>";
}

$d = $data['dados'];
$e = isset($d['endereco']) && is_array($d['endereco']) ? $d['endereco'] : [];

/* ================= FORMATAR CAMPOS ================= */
$cpfOut   = cpfFmt($d['cpf'] ?? '');
$nome     = safe($d['nome'] ?? '');
$situacao = safe($d['situacao'] ?? '');
$nasc     = safe($d['dataNascimento'] ?? '');
$idade    = idadeFmt($nasc);
$diasNiver = diasParaAniversario($nasc);
$sexo     = safe($d['sexo'] ?? '');
$mae      = safe($d['mae'] ?? '');
$ocup     = safe($d['ocupacao'] ?? '');
$unid     = safe($d['unidadeAdministrativa'] ?? '');
$natureza = safe($d['naturezaOcupacao'] ?? '');
$estrang  = safe($d['estrangeiro'] ?? '');
$atualiz  = safe($d['dataUltimaAtualizacao'] ?? '');

/* ENDEREÇO */
$logra = safe($e['logradouro'] ?? '');
$num   = safe($e['numero'] ?? '');
$compl = safe($e['complemento'] ?? '');
$bairro = safe($e['bairro'] ?? '');
$cep    = safe($e['cep'] ?? '');
$cidade = safe($e['cidade'] ?? '');
$uf     = safe($e['uf'] ?? '');

/* ================= SAÍDA FORMATADA ================= */
$txt  = "🕵️ <b>CONSULTA DE CPF (PPE)</b> 🕵️\n\n";
$txt .= "° <b>CPF:</b> <code>{$cpfOut}</code>\n";
$txt .= "° <b>Nome:</b> <code>{$nome}</code>\n";
$txt .= "° <b>Situação:</b> <code>{$situacao}</code>\n";
$txt .= "° <b>Sexo:</b> <code>{$sexo}</code>\n";

if ($nasc !== 'SEM INFORMAÇÃO') {
    $txt .= "° <b>Nascimento:</b> <code>{$nasc} ({$idade})</code>\n";
    if ($diasNiver !== '') $txt .= "° <b>🎂 Aniversário:</b> <code>{$diasNiver}</code>\n";
} else {
    $txt .= "° <b>Nascimento:</b> <code>SEM INFORMAÇÃO</code>\n";
}

$txt .= "\n° <b>Mãe:</b> <code>{$mae}</code>\n";
$txt .= "° <b>Ocupação:</b> <code>{$ocup}</code>\n";
$txt .= "° <b>Natureza da Ocupação:</b> <code>{$natureza}</code>\n";
$txt .= "° <b>Unidade Administrativa:</b> <code>{$unid}</code>\n";
$txt .= "° <b>Estrangeiro:</b> <code>{$estrang}</code>\n";
$txt .= "° <b>Última Atualização:</b> <code>{$atualiz}</code>\n\n";

$txt .= "° <b>ENDEREÇO:</b>\n\n";
$txt .= "° <b>Logradouro:</b> <code>{$logra}</code>\n";
$txt .= "° <b>Número:</b> <code>{$num}</code>\n";
$txt .= "° <b>Complemento:</b> <code>{$compl}</code>\n";
$txt .= "° <b>Bairro:</b> <code>{$bairro}</code>\n";
$txt .= "° <b>CEP:</b> <code>{$cep}</code>\n";
$txt .= "° <b>Cidade:</b> <code>{$cidade}</code>\n";
$txt .= "° <b>UF:</b> <code>{$uf}</code>\n";

return $txt;
?>