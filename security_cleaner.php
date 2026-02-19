<?php
/**
 * Sistema de Limpeza de Security.json
 * - Remove usuários inativos há mais de 30 dias
 * - Otimiza tamanho do arquivo
 * - Mantém apenas dados relevantes
 */

declare(strict_types=1);

define('SECURITY_FILE_PATH', __DIR__ . '/data/security.json');
define('INACTIVITY_DAYS', 30); // remove após 30 dias sem uso
define('BACKUP_BEFORE_CLEAN', true);

class SecurityCleaner {
    
    /**
     * Limpa usuários inativos
     */
    public static function clean(): array {
        if (!file_exists(SECURITY_FILE_PATH)) {
            return ['error' => 'Arquivo não encontrado'];
        }
        
        // Backup antes de limpar
        if (BACKUP_BEFORE_CLEAN) {
            $backup = SECURITY_FILE_PATH . '.backup_' . date('Y-m-d_His');
            @copy(SECURITY_FILE_PATH, $backup);
        }
        
        // Carrega dados
        $fp = @fopen(SECURITY_FILE_PATH, 'c+');
        if (!$fp) return ['error' => 'Não foi possível abrir o arquivo'];
        
        @flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content ?: '{}', true);
        
        if (!is_array($data)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return ['error' => 'Dados inválidos'];
        }
        
        $now = time();
        $threshold = $now - (INACTIVITY_DAYS * 86400);
        
        $beforeCount = count($data);
        $beforeSize = strlen($content);
        $removed = 0;
        
        // Remove usuários inativos
        foreach ($data as $userId => $info) {
            $lastActivity = (float)($info['last'] ?? 0);
            
            // Remove se inativo há mais de X dias E sem ban ativo
            if ($lastActivity < $threshold && ($info['ban_until'] ?? 0) <= $now) {
                unset($data[$userId]);
                $removed++;
            }
        }
        
        // Salva dados limpos
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);
        
        $afterCount = count($data);
        $afterSize = filesize(SECURITY_FILE_PATH);
        
        return [
            'success' => true,
            'before' => [
                'users' => $beforeCount,
                'size_kb' => round($beforeSize / 1024, 2)
            ],
            'after' => [
                'users' => $afterCount,
                'size_kb' => round($afterSize / 1024, 2)
            ],
            'removed' => $removed,
            'saved_kb' => round(($beforeSize - $afterSize) / 1024, 2),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Estatísticas do arquivo
     */
    public static function stats(): array {
        if (!file_exists(SECURITY_FILE_PATH)) {
            return ['error' => 'Arquivo não encontrado'];
        }
        
        $content = @file_get_contents(SECURITY_FILE_PATH);
        $data = json_decode($content ?: '{}', true);
        
        if (!is_array($data)) {
            return ['error' => 'Dados inválidos'];
        }
        
        $now = time();
        $threshold = $now - (INACTIVITY_DAYS * 86400);
        
        $stats = [
            'total_users' => count($data),
            'file_size_kb' => round(strlen($content) / 1024, 2),
            'file_size_mb' => round(strlen($content) / 1024 / 1024, 2),
            'active_users' => 0,
            'inactive_users' => 0,
            'banned_users' => 0,
            'last_modified' => date('Y-m-d H:i:s', filemtime(SECURITY_FILE_PATH))
        ];
        
        foreach ($data as $info) {
            $lastActivity = (float)($info['last'] ?? 0);
            $banUntil = (float)($info['ban_until'] ?? 0);
            
            if ($banUntil > $now) {
                $stats['banned_users']++;
            }
            
            if ($lastActivity >= $threshold) {
                $stats['active_users']++;
            } else {
                $stats['inactive_users']++;
            }
        }
        
        $stats['can_save_kb'] = round(
            ($stats['inactive_users'] * 150) / 1024, 
            2
        ); // estimativa
        
        return $stats;
    }
    
    /**
     * Remove usuário específico
     */
    public static function removeUser(int $userId): bool {
        if (!file_exists(SECURITY_FILE_PATH)) return false;
        
        $fp = @fopen(SECURITY_FILE_PATH, 'c+');
        if (!$fp) return false;
        
        @flock($fp, LOCK_EX);
        $content = stream_get_contents($fp);
        $data = json_decode($content ?: '{}', true);
        
        if (!is_array($data)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return false;
        }
        
        if (!isset($data[$userId])) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            return false;
        }
        
        unset($data[$userId]);
        
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        @flock($fp, LOCK_UN);
        @fclose($fp);
        
        return true;
    }
}

// ===== EXECUÇÃO VIA CRON OU URL =====
if (php_sapi_name() === 'cli' || ($_GET['clean_security'] ?? '') === '1') {
    $result = SecurityCleaner::clean();
    
    if (php_sapi_name() === 'cli') {
        echo "=== SECURITY CLEANUP ===\n";
        if (isset($result['error'])) {
            echo "Error: {$result['error']}\n";
        } else {
            echo "Removed: {$result['removed']} users\n";
            echo "Before: {$result['before']['users']} users, {$result['before']['size_kb']} KB\n";
            echo "After: {$result['after']['users']} users, {$result['after']['size_kb']} KB\n";
            echo "Saved: {$result['saved_kb']} KB\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ===== STATS VIA URL =====
if (($_GET['security_stats'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode(SecurityCleaner::stats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
