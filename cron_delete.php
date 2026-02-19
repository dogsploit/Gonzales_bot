<?php
/**
 * cron_delete.php
 * - Apaga mensagens da fila (resultado, erro e comando original).
 * - Funciona em grupos e tópicos (forum). deleteMessage não precisa de message_thread_id.
 * - Loga falhas para você ver exatamente o motivo.
 */

declare(strict_types=1);

//////////////////// CONFIG ////////////////////
const BOT_TOKEN = 'BOT_TOKEN';
const API_URL   = 'https://api.telegram.org/bot' . BOT_TOKEN . '/';

const DB_HOST    = 'localhost';
const DB_NAME    = 'u937550989_cron_delete';
const DB_USER    = 'u937550989_cron_delete';
const DB_PASS    = 'w23406891W@#';
const DB_CHARSET = 'utf8mb4';

date_default_timezone_set('America/Sao_Paulo');

//////////////////// LOG ////////////////////
function cron_log(string $msg): void {
  @file_put_contents(
    __DIR__ . '/cron_delete.log',
    '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
    FILE_APPEND
  );
}

//////////////////// HTTP ////////////////////
function tg(string $method, array $params): array {
  $ch = curl_init(API_URL . $method);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 20,
  ]);

  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($raw === false) {
    cron_log("HTTP ERROR {$method}: {$err}");
    return ['ok' => false, 'error' => $err, 'http_code' => $code];
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    cron_log("JSON ERROR {$method}: HTTP {$code} RAW=" . substr($raw, 0, 250));
    return ['ok' => false, 'error' => 'invalid_json', 'http_code' => $code];
  }

  // loga erro do Telegram se existir
  if (($json['ok'] ?? false) !== true) {
    $desc = (string)($json['description'] ?? 'unknown_error');
    cron_log("TG ERROR {$method}: HTTP {$code} DESC={$desc} PARAMS=" . json_encode($params));
  }

  return $json;
}

//////////////////// DB ////////////////////
function db(): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}

//////////////////// DELETE HELPER ////////////////////
function try_delete(int $chatId, int $messageId): void {
  if ($chatId === 0 || $messageId === 0) return;

  // Se já foi apagada, o Telegram pode retornar “message to delete not found”
  // e isso é normal, então a gente só loga como INFO.
  $res = tg('deleteMessage', [
    'chat_id' => $chatId,
    'message_id' => $messageId
  ]);

  if (($res['ok'] ?? false) !== true) {
    $desc = (string)($res['description'] ?? '');
    // não poluir log por “não encontrado”
    if (stripos($desc, 'message to delete not found') !== false) {
      cron_log("INFO deleteMessage: msg not found chat_id={$chatId} message_id={$messageId}");
      return;
    }
    if (stripos($desc, 'message can\'t be deleted') !== false || stripos($desc, "can't be deleted") !== false) {
      cron_log("WARN deleteMessage: can't delete chat_id={$chatId} message_id={$messageId} DESC={$desc}");
      return;
    }
    cron_log("WARN deleteMessage failed chat_id={$chatId} message_id={$messageId} DESC={$desc}");
  }
}

//////////////////// EXECUÇÃO ////////////////////
try {
  $pdo = db();

  // pega mensagens vencidas (aumente LIMIT se quiser)
  $stmt = $pdo->prepare("
    SELECT id, chat_id, result_msg_id, orig_msg_id
    FROM delete_queue
    WHERE delete_at <= :now
    ORDER BY id ASC
    LIMIT 100
  ");
  $stmt->execute([':now' => time()]);
  $rows = $stmt->fetchAll();

  if (!$rows) {
    // cron_log("Nada para apagar."); // se quiser logar
    exit;
  }

  foreach ($rows as $row) {
    $id        = (int)($row['id'] ?? 0);
    $chatId    = (int)($row['chat_id'] ?? 0);
    $resultId  = (int)($row['result_msg_id'] ?? 0);
    $origId    = (int)($row['orig_msg_id'] ?? 0);

    // apaga resultado (isso inclui erro também, se você colocou na fila como result_msg_id)
    if ($resultId > 0) {
      try_delete($chatId, $resultId);
    }

    // apaga comando original
    if ($origId > 0) {
      try_delete($chatId, $origId);
    } else {
      // isso vai te mostrar claramente quando no tópico o orig_msg_id está vindo 0
      cron_log("INFO orig_msg_id vazio (0). id={$id} chat_id={$chatId} result_msg_id={$resultId}");
    }

    // remove da fila SEMPRE (apagou ou não)
    $del = $pdo->prepare("DELETE FROM delete_queue WHERE id = :id");
    $del->execute([':id' => $id]);
  }

} catch (Throwable $e) {
  cron_log("FATAL: " . $e->getMessage());
}