<?php
/**
 * consultas/bin.php
 *
 * Usado pelo bot via runConsulta('bin', $bin)
 *
 * Regras:
 * - BIN vem em $ARG (pode vir com espaços, traços, etc).
 * - Valida BIN (somente dígitos, tamanho entre 6 e 8).
 * - Se BIN inválida → "⚠️ BIN inválida!"  (sem assinatura; bot.php cuida do botão APAGAR).
 * - Se BIN não encontrada → "⚠️ BIN não foi encontrada!" (também sem assinatura).
 * - Se OK → retorna texto HTML com labels em negrito e valores em <code>...</code>.
 *
 * Exemplo de endpoint:
 *   https://lookup.binlist.net/45717360
 */

if (!isset($ARG)) {
    return "⚠️ BIN inválida!";
}

// ===== Helpers =====
if (!function_exists('bin_esc')) {
    function bin_esc($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('bin_val')) {
    function bin_val($v, $default = 'Sem informação') {
        if (is_bool($v)) {
            return $v ? 'Sim' : 'Não';
        }
        $v = trim((string)$v);
        if ($v === '' || strtoupper($v) === 'NULL') {
            return $default;
        }
        return bin_esc($v);
    }
}

// ===== BIN vinda do comando =====
$binRaw = (string)$ARG;
$bin    = preg_replace('/\D+/', '', $binRaw);

// Validação simples de BIN (só dígitos, entre 6 e 8)
if ($bin === '' || strlen($bin) < 6 || strlen($bin) > 8) {
    return "⚠️ BIN inválida!";
}

// ===== Chamada da API =====
// Se quiser, depois você troca esse endpoint por outro, mas este é o que você mandou:
$endpoint = 'https://lookup.binlist.net/' . urlencode($bin);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'User-Agent: BotBIN/1.0'
    ],
]);
$res      = curl_exec($ch);
$err      = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Erro de rede
if ($err) {
    return "⚠️ Erro ao consultar a API de BIN.\n<code>" . bin_esc($err) . "</code>";
}

// BIN não encontrada (binlist normalmente devolve 404)
if ($httpCode >= 400 || !$res) {
    return "⚠️ BIN não foi encontrada!";
}

// Decodifica JSON
$data = json_decode($res, true);
if (!is_array($data)) {
    return "⚠️ BIN não foi encontrada!";
}

// ===== Extração dos dados =====
$number  = is_array($data['number']  ?? null) ? $data['number']  : [];
$country = is_array($data['country'] ?? null) ? $data['country'] : [];
$bank    = is_array($data['bank']    ?? null) ? $data['bank']    : [];

$scheme   = bin_val($data['scheme']  ?? '');
$type     = bin_val($data['type']    ?? '');
$brand    = bin_val($data['brand']   ?? '');
$prepaid  = array_key_exists('prepaid', $data) ? bin_val($data['prepaid']) : 'Sem informação';

$length   = bin_val($number['length'] ?? '');
$luhn     = array_key_exists('luhn', $number) ? bin_val($number['luhn']) : 'Sem informação';

$ct_name   = bin_val($country['name']     ?? '');
$ct_alpha2 = bin_val($country['alpha2']   ?? '');
$ct_emoji  = bin_val($country['emoji']    ?? '');
$ct_curr   = bin_val($country['currency'] ?? '');
$ct_num    = bin_val($country['numeric']  ?? '');
$ct_lat    = bin_val($country['latitude'] ?? '');
$ct_lon    = bin_val($country['longitude']?? '');

$bk_name = bin_val($bank['name'] ?? '');
$bk_url  = bin_val($bank['url']  ?? 'Sem informação');
$bk_tel  = bin_val($bank['phone']?? 'Sem informação');
$bk_city = bin_val($bank['city'] ?? 'Sem informação');

// ===== Montagem do texto =====
$texto  = "🕵️ <b>CONSULTA DE BIN</b> 🕵️\n\n";
$texto .= "° <b>BIN:</b> <code>{$bin}</code>\n\n";

$texto .= "° <b>DADOS DO CARTÃO</b>\n\n";
$texto .= "° <b>Bandeira (scheme):</b> <code>{$scheme}</code>\n";
$texto .= "° <b>Tipo (credit/debit):</b> <code>{$type}</code>\n";
$texto .= "° <b>Brand:</b> <code>{$brand}</code>\n";
$texto .= "° <b>Pré-pago:</b> <code>{$prepaid}</code>\n";
$texto .= "° <b>Tamanho do número:</b> <code>{$length}</code>\n";
$texto .= "° <b>Validação Luhn:</b> <code>{$luhn}</code>\n\n";

$texto .= "° <b>PAÍS / MOEDA</b>\n\n";
$texto .= "° <b>País:</b> <code>{$ct_name}</code>\n";
$texto .= "° <b>Código ISO:</b> <code>{$ct_alpha2}</code>\n";
$texto .= "° <b>Código numérico:</b> <code>{$ct_num}</code>\n";
$texto .= "° <b>Moeda:</b> <code>{$ct_curr}</code>\n";
$texto .= "° <b>Emoji:</b> <code>{$ct_emoji}</code>\n";
$texto .= "° <b>Latitude:</b> <code>{$ct_lat}</code>\n";
$texto .= "° <b>Longitude:</b> <code>{$ct_lon}</code>\n\n";

$texto .= "° <b>BANCO EMISSOR</b>\n\n";
$texto .= "° <b>Nome do banco:</b> <code>{$bk_name}</code>\n";
$texto .= "° <b>Site:</b> <code>{$bk_url}</code>\n";
$texto .= "° <b>Telefone:</b> <code>{$bk_tel}</code>\n";
$texto .= "° <b>Cidade:</b> <code>{$bk_city}</code>\n";

return $texto;