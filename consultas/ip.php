<?php
/**
 * consultas/ip.php
 * Entrada: $ARG = IP informado pelo usuário
 * Saída: texto HTML formatado (sem rodapé interno)
 */

if (!isset($ARG)) {
    return "⚠️ IP inválido.";
}

$ip = trim($ARG);

// ===== Validação =====
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return "⚠️ <b>IP inválido!</b>\n\nUse: <code>/ip 8.8.8.8</code>";
}

// ===== Chamada da API =====
$apiUrl = "http://ip-api.com/json/" . urlencode($ip);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    return "⚠️ Erro ao consultar API de IP.\n<code>$err</code>";
}

$data = json_decode($response, true);
if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
    return "⚠️ IP não encontrado!";
}

// Campos
$country     = $data['country']     ?? 'Sem informação';
$countryCode = $data['countryCode'] ?? 'Sem informação';
$region      = $data['regionName']  ?? 'Sem informação';
$regionCode  = $data['region']      ?? 'Sem informação';
$city        = $data['city']        ?? 'Sem informação';
$zip         = $data['zip']         ?? 'Sem informação';
$lat         = $data['lat']         ?? 'Sem informação';
$lon         = $data['lon']         ?? 'Sem informação';
$tz          = $data['timezone']    ?? 'Sem informação';
$isp         = $data['isp']         ?? 'Sem informação';
$org         = $data['org']         ?? 'Sem informação';
$as          = $data['as']          ?? 'Sem informação';

// ===== Montagem =====
$txt  = "🕵️ <b>CONSULTA DE IP</b> 🕵️\n\n";

$txt .= "° <b>IP Pesquisado:</b> <code>$ip</code>\n\n";

$txt .= "° <b>LOCALIZAÇÃO</b>\n\n";
$txt .= "° <b>País:</b> <code>$country ($countryCode)</code>\n";
$txt .= "° <b>Região:</b> <code>$region ($regionCode)</code>\n";
$txt .= "° <b>Cidade:</b> <code>$city</code>\n";
$txt .= "° <b>CEP:</b> <code>$zip</code>\n";
$txt .= "° <b>Latitude:</b> <code>$lat</code>\n";
$txt .= "° <b>Longitude:</b> <code>$lon</code>\n";
$txt .= "° <b>Timezone:</b> <code>$tz</code>\n\n";

$txt .= "° <b>INFORMAÇÕES DA REDE</b>\n\n";
$txt .= "° <b>ISP:</b> <code>$isp</code>\n";
$txt .= "° <b>Organização:</b> <code>$org</code>\n";
$txt .= "° <b>ASN:</b> <code>$as</code>\n";

return $txt; // assinatura adicionada pelo bot.php