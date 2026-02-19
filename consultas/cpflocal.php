<?php
// consultas/cpfsisregi.php — Base Local (formato Orbyta)
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* =============== CONFIG =============== */
$TOKEN = "z8EY1omtgO0NQRZEO26TayS5iCx1zlMq";
$API_URL = "https://orbyta.online/api/apifullcpf?cpf=";

/* =============== VALIDAÇÃO =============== */
if (!isset($ARG) || trim($ARG) === '') {
    return "⚠️ <b>Informe um CPF para consulta!</b>";
}

$cpf = preg_replace('/\D+/', '', (string)$ARG);
if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
    return "⚠️ <b>CPF inválido!</b>";
}

/* =============== HELPERS =============== */
function safe($v): string {
    if (!isset($v) || $v === '' || $v === null) return 'SEM INFORMAÇÃO';
    $v = trim((string)$v);
    if (in_array(strtolower($v), ['null', 'undefined', 'desconhecido'], true)) return 'SEM INFORMAÇÃO';
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function idadeFmt($dataBr): string {
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataBr, $m)) return '';
    try {
        $nasc = new DateTime("{$m[3]}-{$m[2]}-{$m[1]}");
        $hoje = new DateTime('today');
        return $nasc->diff($hoje)->y . " anos";
    } catch (Throwable $e) {
        return '';
    }
}

function fmtDoc($v): string {
    $v = preg_replace('/\D+/', '', (string)$v);
    if (strlen($v) === 11)
        return substr($v,0,3).'.'.substr($v,3,3).'.'.substr($v,6,3).'-'.substr($v,9,2);
    return $v ?: 'SEM INFORMAÇÃO';
}

function fmtTel($ddd, $num): string {
    $ddd = preg_replace('/\D/', '', (string)$ddd);
    $num = preg_replace('/\D/', '', (string)$num);
    if (!$ddd || !$num) return 'SEM INFORMAÇÃO';
    if (strlen($num) === 9)
        return "({$ddd}) " . substr($num,0,5) . '-' . substr($num,5);
    if (strlen($num) === 8)
        return "({$ddd}) " . substr($num,0,4) . '-' . substr($num,4);
    return "({$ddd}) {$num}";
}

function sexoFmt($v): string {
    $v = strtoupper(trim((string)$v));
    return match($v) {
        'M' => 'Masculino',
        'F' => 'Feminino',
        default => 'Indefinido',
    };
}

function httpJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
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

/* =============== CONSULTA API =============== */
$url = "{$API_URL}{$cpf}&token={$TOKEN}";
$data = httpJson($url);

if (!$data || empty($data['dados_pessoais'])) {
    return "⚠️ <b>CPF não encontrado na base local.</b>";
}

/* =============== EXTRAIR DADOS =============== */
$p = $data['dados_pessoais'];
$fam = $data['familia'] ?? [];
$cont = $data['contatos'] ?? [];
$end = $data['enderecos'] ?? [];
$veic = $data['veiculos'] ?? [];
$fin = $data['financeiro'] ?? [];
$perf = $data['perfil_consumo'] ?? [];

$cpfOut   = fmtDoc($p['cpf'] ?? '');
$cnsOut   = safe($p['cns'] ?? '');
$nomeOut  = safe($p['nome'] ?? '');
$status   = safe($p['status_receita'] ?? '');
$sexoOut  = sexoFmt($p['sexo'] ?? '');
$nascOut  = safe($p['data_nascimento'] ?? '');
$idadeOut = idadeFmt($nascOut);
$nacionalidade = safe($p['nacionalidade'] ?? '');
$naturalidade  = safe($p['naturalidade'] ?? '');
$obitoOut      = safe($p['data_obito'] ?? 'SEM INFORMAÇÃO');
$maeOut        = safe($p['nome_mae'] ?? 'SEM INFORMAÇÃO');

/* =============== MONTAR TEXTO =============== */
$txt  = "🕵️ <b>CONSULTA DE CPF (baseLocal)</b> 🕵️\n\n";
$txt .= "° <b>CPF:</b> <code>{$cpfOut}</code>\n";
$txt .= "° <b>CNS:</b> <code>{$cnsOut}</code>\n";
$txt .= "° <b>Nome:</b> <code>{$nomeOut}</code>\n";
$txt .= "° <b>Status Receita:</b> <code>{$status}</code>\n";
$txt .= "° <b>Sexo:</b> <code>{$sexoOut}</code>\n";
$txt .= "° <b>Nascimento:</b> <code>{$nascOut}</code> <code>({$idadeOut})</code>\n";
$txt .= "°\n";
$txt .= "° <b>Nacionalidade:</b> <code>{$nacionalidade}</code>\n";
$txt .= "° <b>Naturalidade:</b> <code>{$naturalidade}</code>\n";
$txt .= "° <b>Óbito:</b> <code>{$obitoOut}</code>\n\n";

/* FINANCEIRO */
if (!empty($fin)) {
    $renda = safe($fin['renda_estimada'] ?? 'SEM INFORMAÇÃO');
    $score = safe($fin['score']['csb8'] ?? '---');
    $txt .= "° <b>FINANCEIRO:</b>\n\n";
    $txt .= "° <b>Renda Estimada:</b> <code>R$ {$renda}</code>\n";
    $txt .= "° <b>Score:</b> <code>{$score}</code>\n\n";
}

/* MÃE */
$txt .= "° <b>Mãe:</b> <code>{$maeOut}</code>\n\n";

/* PARENTES */
if (!empty($fam)) {
    $txt .= "° <b>PARENTES:</b>\n\n";
    foreach ($fam as $f) {
        $vinc = safe($f['vinculo'] ?? '');
        $nome = safe($f['nome'] ?? '');
        $cpfP = fmtDoc($f['cpf_parente'] ?? '');
        $txt .= "• <code>{$vinc}:</code> <code>{$nome} ({$cpfP})</code>\n\n";
    }
}

/* TELEFONES */
if (!empty($cont['telefones'])) {
    $txt .= "° <b>TELEFONES:</b>\n\n";
    foreach ($cont['telefones'] as $t) {
        $txt .= "<code>" . fmtTel($t['ddd'] ?? '', $t['numero'] ?? '') . " (" . strtoupper($t['tipo'] ?? '') . ")</code>\n";
    }
    $txt .= "\n";
}

/* E-MAILS */
if (!empty($cont['emails'])) {
    $txt .= "° <b>E-MAILS:</b>\n\n";
    foreach ($cont['emails'] as $em) {
        $txt .= "<code>{$em}</code>\n";
    }
    $txt .= "\n";
}

/* ENDEREÇOS */
$totalEnd = count($end);
if ($totalEnd > 0) {
    $txt .= "° <b>ENDEREÇOS ({$totalEnd}):</b>\n\n";
    foreach ($end as $e) {
        $txt .= "<code>{$e['logradouro']}, {$e['numero']} - {$e['bairro']}, {$e['cidade']}/{$e['uf']} - CEP {$e['cep']}</code>\n\n";
    }
}

/* VEÍCULOS */
if (!empty($veic)) {
    $txt .= "° <b>VEÍCULOS:</b>\n\n";
    foreach ($veic as $v) {
        $txt .= "<code>{$v['modelo']} ({$v['ano']})</code>\n";
    }
    $txt .= "\n";
}

/* PERFIL DE CONSUMO */
if (!empty($perf)) {
    $txt .= "° <b>PERFIL DE CONSUMO:</b>\n\n";
    $resumo = [];
    foreach ([
        'credito_pessoal_pre_aprovado' => 'Crédito Pessoal',
        'possui_casa_propria' => 'Casa Própria',
        'possui_investimentos' => 'Investimentos',
        'possui_cartao_credito' => 'Cartão Crédito',
        'realizou_viagens' => 'Viagens',
        'tv_cabo' => 'TV a Cabo',
        'banda_larga' => 'Banda Larga'
    ] as $key => $label) {
        if (isset($perf[$key])) {
            $resumo[] = "{$label}: " . ($perf[$key] ? 'SIM' : 'NÃO');
        }
    }
    if ($resumo) $txt .= "<code>" . implode(' | ', $resumo) . "</code>\n\n";
}
return $txt;
?>