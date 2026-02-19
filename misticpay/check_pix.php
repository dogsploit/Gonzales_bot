<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

$paymentId = $_GET['payment_id'] ?? '';

if ($paymentId === '') {
    echo json_encode(['pago'=>false,'erro'=>'payment_id ausente']);
    exit;
}

$payload = [
    'transactionId' => (string)$paymentId
];

$ch = curl_init($config['API_BASE'] . '/api/transactions/check');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'ci: ' . $config['CLIENT_ID'],
        'cs: ' . $config['CLIENT_SECRET'],
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 15,
]);

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code !== 200) {
    echo json_encode([
        'pago' => false,
        'erro' => 'Erro na consulta',
        'http_code' => $code
    ]);
    exit;
}

$data = json_decode($res, true);

$status = strtoupper($data['transaction']['transactionState'] ?? '');

echo json_encode([
    'pago'   => ($status === 'COMPLETO'),
    'status' => $status,
    'raw'    => $data
]);