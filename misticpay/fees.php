<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

$ch = curl_init($config['API_BASE'] . '/api/crypto/fees');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'ci: ' . $config['CLIENT_ID'],
        'cs: ' . $config['CLIENT_SECRET'],
    ],
    CURLOPT_TIMEOUT => 15,
]);

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code !== 200) {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao consultar taxas',
        'http_code' => $code,
        'curl_error' => $err
    ]);
    exit;
}

echo $res;