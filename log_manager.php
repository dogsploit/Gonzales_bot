<?php
/**
 * Sistema de Gerenciamento de Logs
 * - Rotação automática por tamanho
 * - Compressão de logs antigos
 * - Limpeza automática
 */

declare(strict_types=1);

define('LOG_DIR', __DIR__);
define('MAX_LOG_SIZE', 5 * 1024 * 1024); // 5 MB
define('MAX_LOG_FILES', 5); // mantém últimos 5 arquivos
define('LOG_COMPRESS', true); // comprime logs antigos

class LogManager {
    
    /**
     * Rotaciona log se necessário
     */
    public static function rotate(string $logFile): void {
        if (!file_exists($logFile)) return;
        
        $size = filesize($logFile);
        if ($size < MAX_LOG_SIZE) return;
        
        $timestamp = date('Y-m-d_His');
        $rotatedFile = $logFile . '.' . $timestamp;
        
        // Move log atual
        @rename($logFile, $rotatedFile);
        
        // Comprime se habilitado
        if (LOG_COMPRESS && function_exists('gzencode')) {
            $content = @file_get_contents($rotatedFile);
            if ($content !== false) {
                $compressed = gzencode($content, 9);
                @file_put_contents($rotatedFile . '.gz', $compressed);
                @unlink($rotatedFile);
            }
        }
        
        // Cria novo log vazio
        @touch($logFile);
        @chmod($logFile, 0664);
        
        self::cleanup($logFile);
    }
    
    /**
     * Remove logs antigos excedentes
     */
    private static function cleanup(string $logFile): void {
        $dir = dirname($logFile);
        $basename = basename($logFile);
        
        $pattern = $dir . '/' . $basename . '.*';
        $files = glob($pattern);
        
        if (!$files || count($files) <= MAX_LOG_FILES) return;
        
        // Ordena por data de modificação
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
        
        // Remove os mais antigos
        $toRemove = count($files) - MAX_LOG_FILES;
        for ($i = 0; $i < $toRemove; $i++) {
            @unlink($files[$i]);
        }
    }
    
    /**
     * Executa rotação de todos os logs do bot
     */
    public static function rotateAll(): array {
        $logs = [
            LOG_DIR . '/bot.log',
            LOG_DIR . '/cron_delete.log',
        ];
        
        $result = [
            'rotated' => 0,
            'cleaned' => 0,
            'errors' => []
        ];
        
        foreach ($logs as $log) {
            try {
                $sizeBefore = file_exists($log) ? filesize($log) : 0;
                
                if ($sizeBefore >= MAX_LOG_SIZE) {
                    self::rotate($log);
                    $result['rotated']++;
                }
                
            } catch (Throwable $e) {
                $result['errors'][] = basename($log) . ': ' . $e->getMessage();
            }
        }
        
        return $result;
    }
    
    /**
     * Retorna estatísticas dos logs
     */
    public static function getStats(): array {
        $logs = [
            'bot.log' => LOG_DIR . '/bot.log',
            'cron_delete.log' => LOG_DIR . '/cron_delete.log',
        ];
        
        $stats = [];
        
        foreach ($logs as $name => $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $stats[$name] = [
                    'size_bytes' => $size,
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'last_modified' => date('Y-m-d H:i:s', filemtime($path)),
                    'needs_rotation' => $size >= MAX_LOG_SIZE
                ];
                
                // Conta arquivos rotacionados
                $pattern = dirname($path) . '/' . basename($path) . '.*';
                $rotated = glob($pattern);
                $stats[$name]['rotated_count'] = $rotated ? count($rotated) : 0;
            }
        }
        
        return $stats;
    }
}

// ===== EXECUÇÃO VIA CRON OU URL =====
if (php_sapi_name() === 'cli' || ($_GET['rotate_logs'] ?? '') === '1') {
    $result = LogManager::rotateAll();
    
    if (php_sapi_name() === 'cli') {
        echo "=== LOG ROTATION ===\n";
        echo "Rotated: {$result['rotated']}\n";
        echo "Cleaned: {$result['cleaned']}\n";
        if ($result['errors']) {
            echo "Errors:\n";
            foreach ($result['errors'] as $err) {
                echo "  - $err\n";
            }
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
    exit;
}

// ===== STATS VIA URL =====
if (($_GET['log_stats'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode(LogManager::getStats(), JSON_PRETTY_PRINT);
    exit;
}
