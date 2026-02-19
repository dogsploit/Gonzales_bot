<?php
/**
 * Sistema de Manutenção Automática
 * - Executa todas as tarefas de manutenção
 * - Deve ser rodado via CRON diariamente
 * 
 * Adicione ao crontab:
 * 0 3 * * * php /path/to/maintenance.php
 * 
 * Ou via URL (menos recomendado):
 * https://meuvpsbr.shop/maintenance.php?key=SENHA_SECRETA
 */

declare(strict_types=1);

define('MAINTENANCE_KEY', 'gonzales_maintenance_2026'); // TROQUE POR UMA SENHA SEGURA

// Proteção de acesso
if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== MAINTENANCE_KEY) {
        http_response_code(403);
        die('Access denied');
    }
}

// Carrega dependências
require_once __DIR__ . '/log_manager.php';
require_once __DIR__ . '/security_cleaner.php';
require_once __DIR__ . '/backup_manager.php';

class Maintenance {
    
    private array $results = [];
    
    /**
     * Executa todas as tarefas
     */
    public function runAll(): array {
        $this->log("=== INICIANDO MANUTENÇÃO ===");
        $start = microtime(true);
        
        // 1. Rotação de logs
        $this->log("1. Rotacionando logs...");
        $this->results['logs'] = LogManager::rotateAll();
        
        // 2. Limpeza de security.json
        $this->log("2. Limpando security.json...");
        $this->results['security'] = SecurityCleaner::clean();
        
        // 3. Limpeza de pagamentos vencidos
        $this->log("3. Limpando pagamentos vencidos...");
        $this->results['payments'] = $this->cleanExpiredPayments();
        
        // 4. Backup automático
        $this->log("4. Criando backup...");
        $this->results['backup'] = BackupManager::create();
        
        // 5. Otimização de banco
        $this->log("5. Otimizando banco de dados...");
        $this->results['database'] = $this->optimizeDatabase();
        
        // 6. Verificação de integridade
        $this->log("6. Verificando integridade...");
        $this->results['integrity'] = $this->checkIntegrity();
        
        $elapsed = round(microtime(true) - $start, 2);
        $this->log("=== MANUTENÇÃO CONCLUÍDA ({$elapsed}s) ===");
        
        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'duration_seconds' => $elapsed,
            'results' => $this->results
        ];
    }
    
    /**
     * Limpa pagamentos vencidos
     */
    private function cleanExpiredPayments(): array {
        $file = __DIR__ . '/vip/payments.json';
        
        if (!file_exists($file)) {
            return ['error' => 'Arquivo não encontrado'];
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            return ['error' => 'Dados inválidos'];
        }
        
        $now = time();
        $removed = 0;
        $beforeCount = count($data);
        
        foreach ($data as $id => $payment) {
            $createdAt = (int)($payment['created_at'] ?? 0);
            $status = (string)($payment['status'] ?? '');
            
            // Remove pagamentos pendentes com mais de 7 dias
            if ($status === 'PENDING' && ($now - $createdAt) > (7 * 86400)) {
                unset($data[$id]);
                $removed++;
            }
            
            // Remove pagamentos concluídos com mais de 30 dias
            if ($status === 'COMPLETED' && ($now - $createdAt) > (30 * 86400)) {
                unset($data[$id]);
                $removed++;
            }
        }
        
        if ($removed > 0) {
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        
        return [
            'success' => true,
            'before' => $beforeCount,
            'after' => count($data),
            'removed' => $removed
        ];
    }
    
    /**
     * Otimiza banco de dados
     */
    private function optimizeDatabase(): array {
        try {
            $dsn = "mysql:host=localhost;dbname=u937550989_cron_delete;charset=utf8mb4";
            $pdo = new PDO($dsn, 'u937550989_cron_delete', 'w23406891W@#', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Remove registros processados antigos (mais de 7 dias)
            $stmt = $pdo->prepare("DELETE FROM delete_queue WHERE delete_at < :threshold");
            $stmt->execute([':threshold' => time() - (7 * 86400)]);
            $deletedQueue = $stmt->rowCount();
            
            // Otimiza tabela
            $pdo->exec("OPTIMIZE TABLE delete_queue");
            $pdo->exec("OPTIMIZE TABLE lgpd_consentimentos");
            
            return [
                'success' => true,
                'deleted_old_queue' => $deletedQueue,
                'optimized_tables' => 2
            ];
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica integridade de arquivos
     */
    private function checkIntegrity(): array {
        $files = [
            'bot.php',
            'cron_delete.php',
            'force_join.php',
            'vip/users.json',
            'vip/payments.json',
            'data/security.json',
        ];
        
        $status = 'OK';
        $issues = [];
        
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            
            if (!file_exists($path)) {
                $issues[] = "Arquivo não encontrado: {$file}";
                $status = 'ERROR';
                continue;
            }
            
            if (!is_readable($path)) {
                $issues[] = "Arquivo não legível: {$file}";
                $status = 'ERROR';
            }
            
            if (!is_writable($path) && strpos($file, '.json') !== false) {
                $issues[] = "Arquivo não gravável: {$file}";
                $status = 'WARNING';
            }
            
            // Valida JSON
            if (strpos($file, '.json') !== false) {
                $content = file_get_contents($path);
                $json = json_decode($content, true);
                
                if ($json === null && $content !== 'null') {
                    $issues[] = "JSON inválido: {$file}";
                    $status = 'ERROR';
                }
            }
        }
        
        return [
            'status' => $status,
            'issues' => $issues
        ];
    }
    
    /**
     * Log de manutenção
     */
    private function log(string $message): void {
        $logFile = __DIR__ . '/maintenance.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        
        file_put_contents($logFile, $line, FILE_APPEND);
        
        if (php_sapi_name() === 'cli') {
            echo $line;
        }
    }
}

// ===== EXECUÇÃO =====
$maintenance = new Maintenance();
$result = $maintenance->runAll();

// Output
if (php_sapi_name() === 'cli') {
    echo "\n=== RESUMO ===\n";
    echo "Logs rotacionados: " . ($result['results']['logs']['rotated'] ?? 0) . "\n";
    echo "Security limpo: " . ($result['results']['security']['removed'] ?? 0) . " usuários\n";
    echo "Pagamentos limpos: " . ($result['results']['payments']['removed'] ?? 0) . "\n";
    echo "Backup criado: " . ($result['results']['backup']['success'] ? 'SIM' : 'NÃO') . "\n";
    echo "Database otimizado: " . ($result['results']['database']['success'] ? 'SIM' : 'NÃO') . "\n";
    echo "Integridade: " . ($result['results']['integrity']['status'] ?? 'UNKNOWN') . "\n";
    echo "\nTempo total: {$result['duration_seconds']}s\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
