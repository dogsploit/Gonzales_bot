<?php
/**
 * Limpeza automática de PIX vencidos
 * Executado via CRON
 */

date_default_timezone_set('America/Sao_Paulo');

$paymentsFile = __DIR__ . '/../vip/payments.json';

// arquivo não existe
if (!file_exists($paymentsFile)) {
    exit("payments.json não encontrado\n");
}

$payments = json_decode(file_get_contents($paymentsFile), true);
if (!is_array($payments)) {
    exit("payments.json inválido\n");
}

$now = time();
$removed = 0;

foreach ($payments as $paymentId => $p) {
    $expiraEm = (int)($p['expira_em'] ?? 0);

    // remove se expirou ou inválido
    if ($expiraEm <= 0 || $expiraEm < $now) {
        unset($payments[$paymentId]);
        $removed++;
    }
}

// salva se removeu algo
if ($removed > 0) {
    file_put_contents(
        $paymentsFile,
        json_encode($payments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
}

// log opcional
file_put_contents(
    __DIR__ . '/cron.log',
    date('Y-m-d H:i:s') . " removidos={$removed}\n",
    FILE_APPEND
);

echo "OK - removidos: {$removed}\n";