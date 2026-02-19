<?php
/**
 * Webhook MisticPay - VERSÃO ROBUSTA
 * Garante ativação automática 100% dos pagamentos
 */
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

// Log detalhado
function wh_log(string $msg): void {
    file_put_contents(
        __DIR__ . '/webhook.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

// =====================
// Lê o JSON do webhook
// =====================
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

// Log bruto
wh_log('RECEBIDO: ' . $input);

// JSON inválido
if (!is_array($data)) {
    wh_log('ERROR: JSON inválido');
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

// =====================
// Valida tipo e status
// =====================
$transactionType = strtoupper($data['transactionType'] ?? '');
$status = strtoupper($data['status'] ?? '');

wh_log("TYPE: {$transactionType}, STATUS: {$status}");

if ($transactionType !== 'DEPOSITO') {
    wh_log('IGNORED: Não é depósito');
    http_response_code(200);
    echo json_encode(['ignored' => 'not_deposit']);
    exit;
}

if ($status !== 'COMPLETO') {
    wh_log("IGNORED: Status não completo ({$status})");
    http_response_code(200);
    echo json_encode(['ignored' => 'not_completed', 'status' => $status]);
    exit;
}

// =====================
// Transaction ID
// =====================
$transactionId = (string)($data['transactionId'] ?? '');
if ($transactionId === '') {
    wh_log('ERROR: Transaction ID ausente');
    http_response_code(400);
    echo json_encode(['error' => 'missing_transaction_id']);
    exit;
}

wh_log("Transaction ID: {$transactionId}");

// =====================
// Carrega pagamentos
// =====================
$paymentsFile = __DIR__ . '/../vip/payments.json';

if (!file_exists($paymentsFile)) {
    wh_log('ERROR: payments.json não existe');
    http_response_code(500);
    echo json_encode(['error' => 'payments_file_not_found']);
    exit;
}

$fp = @fopen($paymentsFile, 'c+');
if (!$fp) {
    wh_log('ERROR: Não foi possível abrir payments.json');
    http_response_code(500);
    echo json_encode(['error' => 'cannot_open_file']);
    exit;
}

// Lock exclusivo
@flock($fp, LOCK_EX);

$content = stream_get_contents($fp);
$payments = json_decode($content ?: '{}', true);

if (!is_array($payments)) {
    wh_log('ERROR: payments.json inválido');
    @flock($fp, LOCK_UN);
    @fclose($fp);
    http_response_code(500);
    echo json_encode(['error' => 'payments_file_invalid']);
    exit;
}

// PIX não encontrado
if (!isset($payments[$transactionId])) {
    wh_log("IGNORED: Payment não encontrado ({$transactionId})");
    @flock($fp, LOCK_UN);
    @fclose($fp);
    http_response_code(200);
    echo json_encode(['ignored' => 'payment_not_found']);
    exit;
}

// =====================
// Dados do pagamento
// =====================
$payment = $payments[$transactionId];
$userId  = (int)($payment['user_id'] ?? 0);
$dias    = (int)($payment['plano_dias'] ?? 0);

wh_log("User ID: {$userId}, Dias: {$dias}");

if ($userId <= 0 || $dias <= 0) {
    wh_log('ERROR: Dados de pagamento inválidos');
    @flock($fp, LOCK_UN);
    @fclose($fp);
    http_response_code(400);
    echo json_encode(['error' => 'invalid_payment_data']);
    exit;
}

// =====================
// Ativa VIP
// =====================
wh_log("ATIVANDO VIP para user {$userId}...");

require_once __DIR__ . '/../bot.php';

try {
    vip_add_days($userId, $dias);
    wh_log("SUCCESS: VIP ativado com sucesso");
} catch (Throwable $e) {
    wh_log("ERROR ao ativar VIP: " . $e->getMessage());
    @flock($fp, LOCK_UN);
    @fclose($fp);
    http_response_code(500);
    echo json_encode(['error' => 'vip_activation_failed', 'message' => $e->getMessage()]);
    exit;
}

// =====================
// Mensagem automática
// =====================
$users = vip_load_users();
$expTs = (int)($users[$userId]['expires_at'] ?? 0);
$exp   = $expTs > 0 ? date('d/m/Y H:i', $expTs) : '—';

$planoNome = match($dias) {
    7 => '1 Semana',
    14 => '2 Semanas',
    30 => '1 Mês',
    180 => '6 Meses',
    default => "{$dias} dias"
};

$msgText = "✅ <b>PAGAMENTO CONFIRMADO!</b>\n\n"
         . "🎉 Sua conta VIP foi ativada com sucesso!\n\n"
         . "📦 <b>Plano:</b> {$planoNome}\n"
         . "📅 <b>Dias:</b> {$dias}\n"
         . "⏳ <b>Válido até:</b> {$exp}\n\n"
         . "🚀 Agora você tem acesso completo às consultas no privado!\n\n"
         . "💬 Use /menu para começar.";

try {
    tg('sendMessage', [
        'chat_id' => $userId,
        'text' => $msgText,
        'parse_mode' => 'HTML'
    ]);
    wh_log("Mensagem enviada ao usuário {$userId}");
} catch (Throwable $e) {
    wh_log("AVISO: Erro ao enviar mensagem: " . $e->getMessage());
    // Não falha o webhook por causa disso
}

// =====================
// Remove PIX do controle
// =====================
unset($payments[$transactionId]);

// Salva arquivo
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($payments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
@flock($fp, LOCK_UN);
@fclose($fp);

wh_log("Payment removido do arquivo");

// =====================
// Resposta OK
// =====================
wh_log("WEBHOOK PROCESSADO COM SUCESSO!");

http_response_code(200);
echo json_encode([
    'ok' => true,
    'user_id' => $userId,
    'dias' => $dias,
    'expires_at' => $exp
]);