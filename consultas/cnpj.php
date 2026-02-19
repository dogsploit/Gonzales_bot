<?php
/**
 * consultas/cnpj.php
 * Consulta de CNPJ — ReceitaWS (otimizado: rápido, estável, completo)
 * Entrada: $ARG
 * Saída: string HTML
 */

$rawArg = isset($ARG) ? trim((string)$ARG) : '';
if ($rawArg === '') {
  return "⚠️ <b>Informe um CNPJ para consulta!</b>";
}

$cnpj = preg_replace('/\D+/', '', $rawArg);

/* ================= HELPERS (seguros) ================= */

if (!function_exists('cnpj_h')) {
  function cnpj_h($v): string {
    $v = is_scalar($v) ? (string)$v : '';
    $v = trim($v);
    if ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'undefined') {
      return "<code>SEM INFORMAÇÃO</code>";
    }
    $v = htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<code>" . mb_strtoupper($v, 'UTF-8') . "</code>";
  }
}

if (!function_exists('cnpj_plain')) {
  function cnpj_plain($v): string {
    // Versão sem uppercase forçado (pra datas/valores ficarem normais)
    $v = is_scalar($v) ? (string)$v : '';
    $v = trim($v);
    if ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'undefined') {
      return "<code>SEM INFORMAÇÃO</code>";
    }
    return "<code>" . htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
  }
}

if (!function_exists('cnpj_bool')) {
  function cnpj_bool($v): string {
    if ($v === true) return "<code>SIM</code>";
    if ($v === false) return "<code>NÃO</code>";
    return "<code>SEM INFORMAÇÃO</code>";
  }
}

if (!function_exists('cnpj_is_valid')) {
  function cnpj_is_valid(string $c): bool {
    if (strlen($c) !== 14) return false;
    if (!ctype_digit($c)) return false;
    if (preg_match('/^(\d)\1{13}$/', $c)) return false;

    $calc = function(array $w, int $len) use ($c): int {
      $sum = 0;
      for ($i = 0; $i < $len; $i++) $sum += ((int)$c[$i]) * $w[$i];
      $mod = $sum % 11;
      return ($mod < 2) ? 0 : 11 - $mod;
    };

    $w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
    $w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];

    $d1 = $calc($w1, 12);
    if ((int)$c[12] !== $d1) return false;

    $d2 = $calc($w2, 13);
    if ((int)$c[13] !== $d2) return false;

    return true;
  }
}

if (!cnpj_is_valid($cnpj)) {
  return "⚠️ <b>CNPJ inválido! Verifique os números informados.</b>";
}

/* ================= REQUISIÇÃO (rápida e robusta) ================= */

$url = "https://www.receitaws.com.br/v1/cnpj/{$cnpj}";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,

  // ✅ evita travar o bot
  CURLOPT_CONNECTTIMEOUT => 3,
  CURLOPT_TIMEOUT        => 8,

  // ✅ gzip (mais rápido)
  CURLOPT_ENCODING       => '',

  // ✅ headers
  CURLOPT_HTTPHEADER     => [
    'Accept: application/json',
    'Connection: keep-alive',
  ],

  // ✅ SSL correto (profissional)
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_SSL_VERIFYHOST => 2,
]);

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || !$res) {
  return "⚠️ <b>Falha ao consultar o serviço de CNPJ.</b>\n<code>TENTE NOVAMENTE</code>";
}

if ($code === 429) {
  return "⚠️ <b>Serviço de CNPJ ocupado (limite atingido).</b>\n<code>TENTE NOVAMENTE EM ALGUNS SEGUNDOS</code>";
}
if ($code < 200 || $code >= 300) {
  return "⚠️ <b>Falha ao consultar o serviço de CNPJ.</b>\n<code>HTTP {$code}</code>";
}

$j = json_decode($res, true);
if (!is_array($j)) {
  return "⚠️ <b>Resposta inválida do serviço de CNPJ.</b>\n<code>TENTE NOVAMENTE</code>";
}

if (($j['status'] ?? '') === 'ERROR') {
  $msg = isset($j['message']) ? (string)$j['message'] : 'CNPJ NÃO ENCONTRADO';
  $msg = mb_strtoupper($msg, 'UTF-8');
  $msg = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  return "⚠️ <b>CNPJ NÃO ENCONTRADO!</b>\n<code>{$msg}</code>";
}

/* ================= MONTAGEM DA RESPOSTA (COMPLETA) ================= */

// Alguns campos vêm com máscara (cnpj, cep). Mantemos.
$cnpjFmt = (string)($j['cnpj'] ?? $cnpj);

$out = [];
$out[] = "🕵️ <b>CONSULTA DE CNPJ</b> 🕵️\n";

// Identificação
$out[] = "° <b>RAZÃO SOCIAL:</b> " . cnpj_h($j['nome'] ?? '');
$out[] = "° <b>NOME FANTASIA:</b> " . cnpj_h($j['fantasia'] ?? '');
$out[] = "° <b>CNPJ:</b> " . cnpj_plain($cnpjFmt);
$out[] = "° <b>TIPO:</b> " . cnpj_h($j['tipo'] ?? '');
$out[] = "° <b>PORTE:</b> " . cnpj_h($j['porte'] ?? '');
$out[] = "° <b>NATUREZA JURÍDICA:</b> " . cnpj_h($j['natureza_juridica'] ?? '');
$out[] = "° <b>CAPITAL SOCIAL:</b> " . cnpj_plain($j['capital_social'] ?? '');

// Situação
$out[] = "\n° <b>SITUAÇÃO CADASTRAL:</b>";
$out[] = "° <b>SITUAÇÃO:</b> " . cnpj_h($j['situacao'] ?? '');
$out[] = "° <b>DATA SITUAÇÃO:</b> " . cnpj_plain($j['data_situacao'] ?? '');
$out[] = "° <b>MOTIVO SITUAÇÃO:</b> " . cnpj_h($j['motivo_situacao'] ?? '');
$out[] = "° <b>ABERTURA:</b> " . cnpj_plain($j['abertura'] ?? '');
$out[] = "° <b>ÚLTIMA ATUALIZAÇÃO:</b> " . cnpj_plain($j['ultima_atualizacao'] ?? '');
$out[] = "° <b>EFR:</b> " . cnpj_h($j['efr'] ?? '');

// Situação especial
$out[] = "\n° <b>SITUAÇÃO ESPECIAL:</b>";
$out[] = "° <b>SITUAÇÃO ESPECIAL:</b> " . cnpj_h($j['situacao_especial'] ?? '');
$out[] = "° <b>DATA SIT. ESPECIAL:</b> " . cnpj_plain($j['data_situacao_especial'] ?? '');

// CNAE principal
$out[] = "\n° <b>ATIVIDADE PRINCIPAL (CNAE):</b>";
$apCode = $j['atividade_principal'][0]['code'] ?? '';
$apText = $j['atividade_principal'][0]['text'] ?? '';
if (is_string($apCode) && trim($apCode) !== '' || is_string($apText) && trim($apText) !== '') {
  $apLine = trim((string)$apCode . " - " . (string)$apText);
  $apLine = trim($apLine, " -");
  $out[] = "• " . cnpj_h($apLine);
} else {
  $out[] = "• <code>SEM INFORMAÇÃO</code>";
}

// CNAEs secundários
$out[] = "\n° <b>ATIVIDADES SECUNDÁRIAS (CNAE):</b>";
if (!empty($j['atividades_secundarias']) && is_array($j['atividades_secundarias'])) {
  $secs = array_slice($j['atividades_secundarias'], 0, 10); // limite 10 pra não ficar gigante
  foreach ($secs as $sec) {
    $c = isset($sec['code']) ? (string)$sec['code'] : '';
    $t = isset($sec['text']) ? (string)$sec['text'] : '';
    $line = trim($c . " - " . $t);
    $line = trim($line, " -");
    $out[] = "• " . cnpj_h($line);
  }
  if (count($j['atividades_secundarias']) > 10) {
    $out[] = "• <code>+ OUTRAS ATIVIDADES...</code>";
  }
} else {
  $out[] = "• <code>SEM INFORMAÇÃO</code>";
}

// Endereço
$logradouro = trim((string)($j['logradouro'] ?? ''));
$numero     = trim((string)($j['numero'] ?? ''));
$compl      = trim((string)($j['complemento'] ?? ''));
$bairro     = trim((string)($j['bairro'] ?? ''));
$mun        = trim((string)($j['municipio'] ?? ''));
$uf         = trim((string)($j['uf'] ?? ''));
$cepFmt     = trim((string)($j['cep'] ?? ''));

$endereco = trim($logradouro . ($numero !== '' ? ", {$numero}" : '') . ($compl !== '' ? " {$compl}" : ''));
$local    = trim($bairro . ($mun !== '' ? " - {$mun}" : '') . ($uf !== '' ? "/{$uf}" : ''));

$out[] = "\n° <b>ENDEREÇO:</b>";
$out[] = "° <b>RUA:</b> " . cnpj_h($endereco);
$out[] = "° <b>LOCAL:</b> " . cnpj_h($local);
$out[] = "° <b>CEP:</b> " . cnpj_plain($cepFmt);

// Contato
$out[] = "\n° <b>CONTATO:</b>";
$out[] = "° <b>TELEFONE:</b> " . cnpj_h($j['telefone'] ?? '');
$out[] = "° <b>E-MAIL:</b> " . cnpj_h($j['email'] ?? '');

// Simples Nacional / SIMEI
$out[] = "\n° <b>REGIME (SIMPL​​​ES / MEI):</b>";

$simples = $j['simples'] ?? null;
if (is_array($simples)) {
  $out[] = "° <b>SIMPLES:</b> " . cnpj_bool($simples['optante'] ?? null);
  $out[] = "° <b>DATA OPÇÃO:</b> " . cnpj_plain($simples['data_opcao'] ?? '');
  $out[] = "° <b>DATA EXCLUSÃO:</b> " . cnpj_plain($simples['data_exclusao'] ?? '');
} else {
  $out[] = "° <b>SIMPLES:</b> <code>SEM INFORMAÇÃO</code>";
}

$simei = $j['simei'] ?? null;
if (is_array($simei)) {
  $out[] = "° <b>SIMEI (MEI):</b> " . cnpj_bool($simei['optante'] ?? null);
  $out[] = "° <b>DATA OPÇÃO:</b> " . cnpj_plain($simei['data_opcao'] ?? '');
  $out[] = "° <b>DATA EXCLUSÃO:</b> " . cnpj_plain($simei['data_exclusao'] ?? '');
} else {
  $out[] = "° <b>SIMEI (MEI):</b> <code>SEM INFORMAÇÃO</code>";
}

// QSA
$out[] = "\n° <b>QUADRO DE SÓCIOS (QSA):</b>";
if (!empty($j['qsa']) && is_array($j['qsa'])) {
  foreach (array_slice($j['qsa'], 0, 8) as $socio) { // limite 8
    $nomeSocio = isset($socio['nome']) ? trim((string)$socio['nome']) : '';
    $qualSocio = isset($socio['qual']) ? trim((string)$socio['qual']) : '';
    $nomeSocio = ($nomeSocio === '') ? 'SEM INFORMAÇÃO' : $nomeSocio;
    $qualSocio = ($qualSocio === '') ? 'SÓCIO' : $qualSocio;

    $nomeSocio = mb_strtoupper(htmlspecialchars($nomeSocio, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'), 'UTF-8');
    $qualSocio = mb_strtoupper(htmlspecialchars($qualSocio, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'), 'UTF-8');

    $out[] = "• <code>{$nomeSocio}</code> ({$qualSocio})";
  }
  if (count($j['qsa']) > 8) $out[] = "• <code>+ OUTROS SÓCIOS...</code>";
} else {
  $out[] = "• <code>SEM INFORMAÇÃO</code>";
}

// ⚠️ Não exibimos "billing" (pagamento/plano) conforme você pediu

return implode("\n", $out);