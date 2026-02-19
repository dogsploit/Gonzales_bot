<?php
// consultas/cpf.php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_URL = 'https://vps-gonzales.duckdns.org/apis/api_credilink.php?cpf=';

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

function sexoFmt($v): string {
    $v = strtoupper(trim((string)$v));
    if ($v === 'M') return 'MASCULINO';
    if ($v === 'F') return 'FEMININO';
    return 'SEM INFORMAÇÃO';
}

function brDate($v): string {
    $v = trim((string)$v);
    if ($v === '' || strtolower($v) === 'null') return 'SEM INFORMAÇÃO';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) return $v;
    return 'SEM INFORMAÇÃO';
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

function moeda($v): string {
    $n = preg_replace('/[^\d.,-]/', '', (string)$v);
    if ($n === '') return 'SEM INFORMAÇÃO';
    return 'R$ ' . number_format((float)$n, 2, ',', '.');
}

function httpJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
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

if (!$data) {
    return "⚠️ <b>Erro de conexão com a API.</b>\nTente novamente em instantes.";
}

if (
    empty($data['ok']) ||
    empty($data['encontrado']) ||
    empty($data['dados_cadastrais']['NOME'])
) {
    return "⚠️ <b>CPF não encontrado na base de dados.</b>";
}

$dc = $data['dados_cadastrais'];
$telefones = is_array($data['telefones'] ?? null) ? $data['telefones'] : [];

/* ================= FORMATAR DADOS ================= */
$nome   = safe($dc['NOME'] ?? '');
$nasc   = brDate($dc['DT_NASCIMENTO'] ?? '');
$idade  = idadeFmt($nasc);
$diasNiver = diasParaAniversario($nasc);
$sexo   = sexoFmt($dc['SEXO'] ?? '');
$mae    = safe($dc['NOME_MAE'] ?? '');
$email  = safe($dc['EMAIL'] ?? '');
$obito  = ($dc['FLAG_OBITO'] ?? '0') === '1' ? '✅ SIM' : '❌ NÃO';
$renda  = moeda($dc['RENDA_PRESUMIDA'] ?? '');
$faixa  = safe($dc['FAIXA_RENDA'] ?? '');

$logradouro = safe(trim(($dc['TIPO_ENDERECO'] ?? '') . ' ' . ($dc['LOGRADOURO'] ?? '')));
$numero     = safe($dc['NUMERO'] ?? '');
$bairro     = safe($dc['BAIRRO'] ?? '');
$cidade     = safe($dc['CIDADE'] ?? '');
$uf         = safe($dc['UF'] ?? '');
$cep        = safe($dc['CEP'] ?? '');

/* ================= RESPOSTA FORMATADA ================= */
$txt  = "🕵️ <b>CONSULTA DE CPF CREDILINK</b> 🕵️\n\n";

$txt .= "° <b>CPF:</b> <code>" . cpfFmt($cpf) . "</code>\n";
$txt .= "° <b>Nome:</b> <code>{$nome}</code>\n";
$txt .= "° <b>Sexo:</b> <code>{$sexo}</code>\n";

if ($nasc !== 'SEM INFORMAÇÃO') {
    $txt .= "° <b>Nascimento:</b> <code>{$nasc} ({$idade})</code>\n";
    if ($diasNiver !== '') $txt .= "° <b>🎂 Aniversário:</b> <code>{$diasNiver}</code>\n";
} else {
    $txt .= "° <b>Nascimento:</b> <code>SEM INFORMAÇÃO</code>\n";
}

$txt .= "\n° <b>Mãe:</b> <code>{$mae}</code>\n\n";
$txt .= "° <b>E-mail:</b> <code>{$email}</code>\n\n";

$txt .= "° <b>Óbito:</b> <code>{$obito}</code>\n\n";
$txt .= "° <b>Renda Presumida:</b> <code>{$renda}</code>\n";
$txt .= "° <b>Faixa de Renda:</b> <code>{$faixa}</code>\n\n";

/* TELEFONES */
if (!empty($telefones)) {
    $txt .= "° <b>Telefones:</b>\n\n";
    foreach ($telefones as $t) {
        $num = preg_replace('/\D+/', '', $t);
        if (strlen($num) >= 10) {
            $ddd = substr($num, 0, 2);
            $resto = substr($num, 2);
            $formatado = (strlen($resto) === 9)
                ? "($ddd) " . substr($resto, 0, 5) . "-" . substr($resto, 5)
                : "($ddd) " . substr($resto, 0, 4) . "-" . substr($resto, 4);
            $txt .= "<code>{$formatado}</code>\n";
        } else {
            $txt .= "<code>{$t}</code>\n";
        }
    }
    $txt .= "\n";
}

/* ENDEREÇO */
$txt .= "° <b>ENDEREÇO:</b>\n\n";
$txt .= "<b>° Logradouro:</b> <code>{$logradouro}</code>\n";
$txt .= "<b>° Número:</b> <code>{$numero}</code>\n";
$txt .= "<b>° Bairro:</b> <code>{$bairro}</code>\n";
$txt .= "<b>° Cidade:</b> <code>{$cidade}</code>\n";
$txt .= "<b>° Estado:</b> <code>{$uf}</code>\n";
$txt .= "<b>° CEP:</b> <code>{$cep}</code>\n\n";

return $txt;
?>