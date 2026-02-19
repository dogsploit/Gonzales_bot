<?php
// consultas/cep.php
// Entrada: $ARG (CEP só com dígitos)
// Saída: string HTML com o resultado OU mensagem de erro

if (!isset($ARG)) {
  return "❗ <b>CEP inválido.</b>\nEx.: <code>/cep 01001000</code>";
}

$cep = preg_replace('/\D+/', '', (string)$ARG);
if (!preg_match('/^\d{8}$/', $cep)) {
  return "❗ <b>CEP inválido.</b>\nEx.: <code>/cep 01001000</code>";
}

// Mapeia UF -> nome completo e região (opcional)
$UF_NOME = [
  'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará',
  'DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso',
  'MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná',
  'PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte',
  'RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo',
  'SE'=>'Sergipe','TO'=>'Tocantins'
];

$UF_REGIAO = [
  'AC'=>'Norte','AL'=>'Nordeste','AP'=>'Norte','AM'=>'Norte','BA'=>'Nordeste','CE'=>'Nordeste',
  'DF'=>'Centro-Oeste','ES'=>'Sudeste','GO'=>'Centro-Oeste','MA'=>'Nordeste','MT'=>'Centro-Oeste',
  'MS'=>'Centro-Oeste','MG'=>'Sudeste','PA'=>'Norte','PB'=>'Nordeste','PR'=>'Sul',
  'PE'=>'Nordeste','PI'=>'Nordeste','RJ'=>'Sudeste','RN'=>'Nordeste',
  'RS'=>'Sul','RO'=>'Norte','RR'=>'Norte','SC'=>'Sul','SP'=>'Sudeste',
  'SE'=>'Nordeste','TO'=>'Norte'
];

// Requisição ViaCEP (mais rápida + estável)
$url = "https://viacep.com.br/ws/{$cep}/json/"; // ✅ padrão

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,

  // ✅ timeouts menores (evita travas)
  CURLOPT_CONNECTTIMEOUT => 3,
  CURLOPT_TIMEOUT        => 5,

  // ✅ gzip (resposta menor/mais rápida)
  CURLOPT_ENCODING       => '',

  // ✅ headers úteis
  CURLOPT_HTTPHEADER     => [
    'Accept: application/json',
    'Connection: keep-alive',
  ],

  // ✅ mantém verificação SSL
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_SSL_VERIFYHOST => 2,
]);

$raw  = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Erro de rede / SSL / timeout
if ($err) {
  return "⚠️ <b>Falha ao consultar o ViaCEP.</b>\nTente novamente.";
}

// HTTP ruim
if ($code < 200 || $code >= 300 || !$raw) {
  return "⚠️ <b>Falha ao consultar o ViaCEP.</b>\nTente novamente.";
}

$js = json_decode($raw, true);
if (!is_array($js)) {
  return "⚠️ <b>Resposta inválida do ViaCEP.</b>\nTente novamente.";
}
if (isset($js['erro']) && $js['erro']) {
  return "⚠️ <b>CEP não encontrado!</b>";
}

// Campos com fallback “Sem informação” (robusto)
$val = function(string $k) use ($js): string {
  $v = $js[$k] ?? '';
  if (!is_string($v)) $v = '';
  $v = trim($v);
  if ($v === '') return 'Sem informação';
  return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$cepFmt      = $val('cep');
$logradouro  = $val('logradouro');
$complemento = $val('complemento');
$bairro      = $val('bairro');
$cidade      = $val('localidade');
$uf          = $val('uf');
$ibge        = $val('ibge');
$gia         = $val('gia');
$ddd         = $val('ddd');
$siafi       = $val('siafi');

// Estado completo / região (usa UF original do JSON se existir)
$ufRaw = strtoupper(trim((string)($js['uf'] ?? '')));
$estadoNome = ($ufRaw !== '' && isset($UF_NOME[$ufRaw])) ? $UF_NOME[$ufRaw] : 'Sem informação';
$regiao     = ($ufRaw !== '' && isset($UF_REGIAO[$ufRaw])) ? $UF_REGIAO[$ufRaw] : 'Sem informação';

// Monta resposta (HTML) — mantém seu formato
$out  = "<b>🕵️ CONSULTA DE CEP</b>\n\n";
$out .= "<b>° CEP:</b> <code>{$cepFmt}</code>\n";
$out .= "<b>° Logradouro:</b> <code>{$logradouro}</code>\n";
$out .= "<b>° Complemento:</b> <code>{$complemento}</code>\n";
$out .= "<b>° Bairro:</b> <code>{$bairro}</code>\n";
$out .= "<b>° Cidade:</b> <code>{$cidade}</code>\n";
$out .= "<b>° UF:</b> <code>{$uf}</code>\n";
$out .= "<b>° Estado completo:</b> <code>".htmlspecialchars($estadoNome, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</code>\n";
$out .= "<b>° Região:</b> <code>".htmlspecialchars($regiao, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</code>\n";
$out .= "<b>° IBGE:</b> <code>{$ibge}</code>\n";
$out .= "<b>° GIA:</b> <code>{$gia}</code>\n";
$out .= "<b>° DDD:</b> <code>{$ddd}</code>\n";
$out .= "<b>° SIAFI:</b> <code>{$siafi}</code>\n";

return $out;