<?php
/**
 * Arquivo de Configuração Centralizado
 * - Todas as configurações do bot em um único lugar
 * - Separação por ambiente (produção/desenvolvimento)
 * - Validação de configurações críticas
 */

return [
    
    // ===== AMBIENTE =====
    'environment' => 'production', // production | development
    
    // ===== BOT TELEGRAM =====
    'bot' => [
        'token' => 'BOT_TOKEN',
        'username' => 'EmonNullbot',
        'admin_id' => 7505318236,
    ],
    
    // ===== BANCO DE DADOS =====
    'database' => [
        'host' => 'localhost',
        'name' => 'u937550989_cron_delete',
        'user' => 'u937550989_cron_delete',
        'pass' => 'w23406891W@#',
        'charset' => 'utf8mb4',
    ],
    
    // ===== SEGURANÇA E ANTIFLOOD =====
    'security' => [
        'window_seconds' => 60,
        'max_events' => 10,
        'ban_seconds' => 30,
        'ban_multiplier' => 2,
        'ban_max_seconds' => 600,
        
        // Antiflood por comando repetido
        'cmd_window_seconds' => 600, // 10 minutos
        'cmd_max_repeat' => 3,
        'cmd_commands_enabled' => ['/placa'], // comandos com antiflood especial
        
        // Bloqueio de consulta repetida (placa)
        'placa_block_ttl' => 18000, // 5 horas
    ],
    
    // ===== AUTO-DELETE =====
    'auto_delete' => [
        'enabled' => true,
        'seconds' => 60,
        'only_groups' => true, // não apaga no privado
    ],
    
    // ===== FORCE JOIN =====
    'force_join' => [
        'enabled' => true,
        'chat_id' => -1002573461775,
        'channel_url' => 'https://t.me/GonzalesCanal',
        'only_private' => true, // não exige em grupos
    ],
    
    // ===== SISTEMA VIP =====
    'vip' => [
        'enabled' => true,
        'paywall_private' => true, // obriga VIP no privado
        'plans' => [
            'vip_7' => ['days' => 7, 'price' => 10.00, 'label' => '1 Semana'],
            'vip_14' => ['days' => 14, 'price' => 15.00, 'label' => '2 Semanas'],
            'vip_30' => ['days' => 30, 'price' => 25.00, 'label' => '1 Mês'],
            'vip_180' => ['days' => 180, 'price' => 120.00, 'label' => '6 Meses'],
        ],
    ],
    
    // ===== PAGAMENTO (MISTICPAY) =====
    'payment' => [
        'api_base' => 'https://api.misticpay.com',
        'client_id' => 'ci_zq3kz1dq09ka5mg',
        'client_secret' => 'cs_t6tawn7spcu63md8fda5rcpwy',
        'timeout' => 20,
    ],
    
    // ===== LOGS =====
    'logs' => [
        'enabled' => true,
        'max_size_mb' => 5,
        'max_files' => 5,
        'compress' => true,
        'auto_rotate' => true,
    ],
    
    // ===== BACKUP =====
    'backup' => [
        'enabled' => true,
        'max_backups' => 10,
        'auto_daily' => true,
    ],
    
    // ===== LIMPEZA AUTOMÁTICA =====
    'cleanup' => [
        'security_json' => [
            'enabled' => true,
            'inactivity_days' => 30,
        ],
        'old_payments' => [
            'enabled' => true,
            'days' => 7,
        ],
    ],
    
    // ===== APIs EXTERNAS =====
    'apis' => [
        'orbyta' => [
            'url' => 'https://orbyta.online/api/apifullcpf',
            'token' => 'z8EY1omtgO0NQRZEO26TayS5iCx1zlMq',
        ],
        'serpro' => [
            'url' => 'https://meuvpsbr.shop/apis/serpr00o.php',
            'apikey' => 'gonzales',
        ],
    ],
    
    // ===== CAMINHOS =====
    'paths' => [
        'root' => __DIR__,
        'data' => __DIR__ . '/data',
        'vip' => __DIR__ . '/vip',
        'backups' => __DIR__ . '/backups',
        'consultas' => __DIR__ . '/consultas',
        'apis' => __DIR__ . '/apis',
    ],
    
    // ===== PERFORMANCE =====
    'performance' => [
        'curl_keepalive' => true,
        'curl_compression' => true,
        'curl_timeout' => 20,
        'curl_connect_timeout' => 5,
    ],
    
    // ===== MENSAGENS =====
    'messages' => [
        'support_url' => 'https://t.me/GonzalesDev',
        'channel_url' => 'https://t.me/GonzalesCanal',
    ],
];
