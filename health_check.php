<?php
/**
 * Sistema de Health Check Avançado
 * - Monitora status de todos os componentes do bot
 * - Verifica conectividade com APIs externas
 * - Valida integridade de arquivos críticos
 * - Fornece métricas de performance
 */

declare(strict_types=1);

class HealthCheck {
    
    private array $results = [];
    private float $startTime;
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    /**
     * Executa todos os checks
     */
    public function runAll(): array {
        $this->checkFileSystem();
        $this->checkDatabase();
        $this->checkTelegramAPI();
        $this->checkExternalAPIs();
        $this->checkResources();
        $this->checkSecurity();
        
        return $this->getReport();
    }
    
    /**
     * Verifica sistema de arquivos
     */
    private function checkFileSystem(): void {
        $files = [
            'bot.php' => __DIR__ . '/bot.php',
            'cron_delete.php' => __DIR__ . '/cron_delete.php',
            'force_join.php' => __DIR__ . '/force_join.php',
            'vip/users.json' => __DIR__ . '/vip/users.json',
            'vip/payments.json' => __DIR__ . '/vip/payments.json',
            'data/security.json' => __DIR__ . '/data/security.json',
        ];
        
        $status = 'OK';
        $details = [];
        
        foreach ($files as $name => $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $writable = is_writable($path);
                
                $details[$name] = [
                    'exists' => true,
                    'writable' => $writable,
                    'size_kb' => round($size / 1024, 2)
                ];
                
                if (!$writable) {
                    $status = 'WARNING';
                }
            } else {
                $details[$name] = ['exists' => false];
                $status = 'ERROR';
            }
        }
        
        $this->results['filesystem'] = [
            'status' => $status,
            'details' => $details
        ];
    }
    
    /**
     * Verifica conexão com banco de dados
     */
    private function checkDatabase(): void {
        try {
            $dsn = "mysql:host=localhost;dbname=u937550989_cron_delete;charset=utf8mb4";
            $pdo = new PDO($dsn, 'u937550989_cron_delete', 'w23406891W@#', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            
            // Testa query simples
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM delete_queue");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->results['database'] = [
                'status' => 'OK',
                'connected' => true,
                'queue_size' => (int)$row['total'],
                'response_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2)
            ];
            
        } catch (Throwable $e) {
            $this->results['database'] = [
                'status' => 'ERROR',
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica API do Telegram
     */
    private function checkTelegramAPI(): void {
        $token = '8142774636:AAGjs1oiZTatk56qNIIg0r0hYTAfy8A0O0E';
        $url = "https://api.telegram.org/bot{$token}/getMe";
        
        $start = microtime(true);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        
        if ($error || $httpCode !== 200) {
            $this->results['telegram_api'] = [
                'status' => 'ERROR',
                'error' => $error ?: "HTTP {$httpCode}",
                'response_time_ms' => $elapsed
            ];
            return;
        }
        
        $data = json_decode($response, true);
        
        $this->results['telegram_api'] = [
            'status' => 'OK',
            'bot_username' => $data['result']['username'] ?? 'unknown',
            'response_time_ms' => $elapsed
        ];
    }
    
    /**
     * Verifica APIs externas
     */
    private function checkExternalAPIs(): void {
        $apis = [
            'MisticPay' => 'https://api.misticpay.com',
            'ViaCEP' => 'https://viacep.com.br/ws/01001000/json/',
        ];
        
        $results = [];
        
        foreach ($apis as $name => $url) {
            $start = microtime(true);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_NOBODY => true, // HEAD request
            ]);
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            
            $results[$name] = [
                'status' => ($error || $httpCode >= 400) ? 'ERROR' : 'OK',
                'http_code' => $httpCode,
                'response_time_ms' => $elapsed,
                'error' => $error ?: null
            ];
        }
        
        $overallStatus = 'OK';
        foreach ($results as $r) {
            if ($r['status'] === 'ERROR') {
                $overallStatus = 'WARNING';
                break;
            }
        }
        
        $this->results['external_apis'] = [
            'status' => $overallStatus,
            'apis' => $results
        ];
    }
    
    /**
     * Verifica recursos do sistema
     */
    private function checkResources(): void {
        $this->results['resources'] = [
            'status' => 'OK',
            'php_version' => PHP_VERSION,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'disk_free_gb' => round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 2),
            'extensions' => [
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'pdo' => extension_loaded('pdo'),
                'zip' => extension_loaded('zip'),
            ]
        ];
    }
    
    /**
     * Verifica segurança
     */
    private function checkSecurity(): void {
        $securityFile = __DIR__ . '/data/security.json';
        $vipFile = __DIR__ . '/vip/users.json';
        
        $status = 'OK';
        $issues = [];
        
        // Verifica tamanho do security.json
        if (file_exists($securityFile)) {
            $size = filesize($securityFile);
            $sizeMB = round($size / 1024 / 1024, 2);
            
            if ($sizeMB > 1) {
                $status = 'WARNING';
                $issues[] = "security.json muito grande ({$sizeMB} MB) - executar limpeza";
            }
            
            $data = json_decode(file_get_contents($securityFile), true);
            $userCount = is_array($data) ? count($data) : 0;
            
            $bannedCount = 0;
            if (is_array($data)) {
                foreach ($data as $info) {
                    if (($info['ban_until'] ?? 0) > time()) {
                        $bannedCount++;
                    }
                }
            }
        } else {
            $userCount = 0;
            $bannedCount = 0;
        }
        
        // Verifica VIPs
        $vipCount = 0;
        if (file_exists($vipFile)) {
            $vipData = json_decode(file_get_contents($vipFile), true);
            if (is_array($vipData)) {
                foreach ($vipData as $info) {
                    if (($info['expires_at'] ?? 0) > time()) {
                        $vipCount++;
                    }
                }
            }
        }
        
        $this->results['security'] = [
            'status' => $status,
            'total_users_tracked' => $userCount,
            'currently_banned' => $bannedCount,
            'active_vips' => $vipCount,
            'issues' => $issues
        ];
    }
    
    /**
     * Gera relatório final
     */
    private function getReport(): array {
        $overallStatus = 'OK';
        
        foreach ($this->results as $check) {
            if ($check['status'] === 'ERROR') {
                $overallStatus = 'ERROR';
                break;
            } elseif ($check['status'] === 'WARNING' && $overallStatus === 'OK') {
                $overallStatus = 'WARNING';
            }
        }
        
        return [
            'overall_status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'total_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            'checks' => $this->results
        ];
    }
    
    /**
     * Retorna status simplificado (para webhook healthcheck)
     */
    public function getSimpleStatus(): array {
        $report = $this->runAll();
        
        return [
            'status' => $report['overall_status'],
            'timestamp' => $report['timestamp'],
            'bot' => $report['checks']['telegram_api']['status'] ?? 'UNKNOWN',
            'database' => $report['checks']['database']['status'] ?? 'UNKNOWN',
        ];
    }
}

// ===== EXECUÇÃO VIA URL =====
if (($_GET['health'] ?? '') === 'full') {
    header('Content-Type: application/json');
    $health = new HealthCheck();
    echo json_encode($health->runAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['health'] ?? '') === 'simple' || ($_GET['health'] ?? '') === '1') {
    header('Content-Type: application/json');
    $health = new HealthCheck();
    echo json_encode($health->getSimpleStatus(), JSON_UNESCAPED_UNICODE);
    exit;
}
