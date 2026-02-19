<?php
// misticpay/criar_pix.php

header('Content-Type: application/json');
date_default_timezone_set('America/Sao_Paulo');

// ================= CONFIG =================
$CLIENT_ID     = 'ci_zq3kz1dq09ka5mg';
$CLIENT_SECRET = 'cs_t6tawn7spcu63md8fda5rcpwy';
$API_URL       = 'https://api.misticpay.com/api/transactions/create';

// ================= PARAMETROS =================
$userId = $_GET['user_id'] ?? null;
$tipo   = $_GET['tipo'] ?? null;

if (!$userId || !$tipo) {
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

// ================= PLANOS =================
$planos = [
    'vip_7' => [
        'dias'  => 7,
        'valor' => 10,
        'label' => '1 Semana'
    ],
    'vip_14' => [
        'dias'  => 14,
        'valor' => 15,
        'label' => '2 Semanas'
    ],
    'vip_30' => [
        'dias'  => 30,
        'valor' => 25,
        'label' => '1 Mês'
    ],
    'vip_180' => [
        'dias'  => 180,
        'valor' => 120,
        'label' => '6 Meses'
    ],
];

if (!isset($planos[$tipo])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Plano inválido']);
    exit;
}

$plano = $planos[$tipo];

// ================= PAYLOAD =================
$transactionId = "tg_{$userId}_" . time();

$payload = [
    'amount' => $plano['valor'],
    'payerName' => "Cliente_{$userId}",
    'payerDocument' => '12345678909',
    'transactionId' => $transactionId,
    'description' => "Plano VIP {$plano['label']} ({$plano['dias']} dias)"
];

// ================= CURL =================
$ch = curl_init($API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "ci: {$CLIENT_ID}",
        "cs: {$CLIENT_SECRET}",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode !== 201) {
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro na requisição',
        'http_code' => $httpCode,
        'curl_error' => $curlErr
    ]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['data']['transactionId'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Resposta inválida']);
    exit;
}

// ================= SALVAR NO PAYMENTS.JSON =================
$paymentsFile = __DIR__ . '/../vip/payments.json';

$fp = @fopen($paymentsFile, 'c+');
if (!$fp) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao abrir payments.json']);
    exit;
}

// Lock exclusivo
@flock($fp, LOCK_EX);

$content = stream_get_contents($fp);
$payments = json_decode($content ?: '{}', true);

if (!is_array($payments)) {
    $payments = [];
}

// Adiciona pagamento com expiração de 24h
$payments[$transactionId] = [
    'user_id'    => (int)$userId,
    'plano_dias' => $plano['dias'],
    'plano_label' => $plano['label'],
    'valor'      => $plano['valor'],
    'status'     => 'PENDING',
    'created_at' => time(),
    'expira_em'  => time() + 86400 // 24 horas
];

// Salva arquivo
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($payments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
@flock($fp, LOCK_UN);
@fclose($fp);

// ================= RETORNO PADRÃO PRO BOT =================
echo json_encode([
    'sucesso'     => true,
    'payment_id'  => $transactionId,
    'plano'       => $tipo,
    'plano_label' => $plano['label'],
    'dias'        => $plano['dias'],
    'valor'       => $plano['valor'],
    'qr_code'     => $data['data']['qrcodeUrl'],
    'copia_cola'  => $data['data']['copyPaste'],
    'expira_em'   => time() + 86400,
    'expira_em_formatado' => date('d/m/Y H:i', time() + 86400)
]);