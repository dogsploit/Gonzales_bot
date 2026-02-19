Isso é um upload do bot de puxar dados; Gonzales_bot, que foi ownado pelo(a) maye, a seguir segue o readme.md;

# 🤖 Bot de Telegram - Sistema Profissional de Consultas

Bot completo com sistema VIP, pagamentos PIX, moderação de grupos e 14 tipos de consultas diferentes.

---

## 📋 ÍNDICE

1. [Características](#características)
2. [Instalação](#instalação)
3. [Configuração](#configuração)
4. [Sistemas Implementados](#sistemas-implementados)
5. [Manutenção](#manutenção)
6. [Comandos](#comandos)
7. [APIs](#apis)
8. [Segurança](#segurança)

---

## ✨ CARACTERÍSTICAS

### Consultas Disponíveis
- **CPF** - 6 bases de dados diferentes
- **CNPJ** - Dados completos da empresa
- **CEP** - Endereço por CEP
- **NOME** - Busca por nome completo
- **TELEFONE** - Dados do titular
- **PLACA** - Informações do veículo
- **IP** - Geolocalização
- **BIN** - Bandeira do cartão
- **CHECKER** - Validação de logins SISREG

### Sistemas Principais
- ✅ Sistema VIP com pagamento PIX automático
- ✅ Force Join (obriga entrada no canal)
- ✅ Auto-delete em grupos
- ✅ Antiflood multinível
- ✅ Moderação de grupos
- ✅ Backup automático
- ✅ Rotação de logs
- ✅ Health check completo

---

## 🚀 INSTALAÇÃO

### Requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Extensões: curl, json, pdo, zip
- Acesso CRON (para tarefas automáticas)

### Passo a Passo

1. **Clone/Upload dos arquivos**
```bash
# Fazer upload de todos os arquivos para:
/home/u937550989/domains/meuvpsbr.shop/public_html/
```

2. **Configurar permissões**
```bash
chmod 664 bot.log
chmod 664 cron_delete.log
chmod 775 vip/
chmod 775 data/
chmod 775 backups/
```

3. **Criar tabelas do banco**
```sql
CREATE TABLE delete_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    result_msg_id INT NOT NULL,
    orig_msg_id INT DEFAULT 0,
    delete_at INT NOT NULL,
    INDEX idx_delete_at (delete_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lgpd_consentimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    hash_consentimento CHAR(64) NOT NULL,
    versao_termos VARCHAR(10) NOT NULL,
    aceito_em DATETIME NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    UNIQUE KEY uniq_user_versao (user_id, versao_termos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

4. **Configurar webhook**
```bash
# URL do webhook:
https://meuvpsbr.shop/bot.php

# Setar via API:
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -d "url=https://meuvpsbr.shop/bot.php"
```

5. **Configurar CRON**
```bash
# Editar crontab
crontab -e

# Adicionar linhas:
# Cron de auto-delete (a cada minuto)
* * * * * php /home/u937550989/domains/meuvpsbr.shop/public_html/cron_delete.php

# Manutenção diária (3h da manhã)
0 3 * * * php /home/u937550989/domains/meuvpsbr.shop/public_html/maintenance.php
```

---

## ⚙️ CONFIGURAÇÃO

### Arquivo config.php
Centralize todas as configurações:

```php
return [
    'bot' => [
        'token' => 'SEU_TOKEN',
        'username' => 'SeuBot',
        'admin_id' => SEU_ID,
    ],
    // ... outras configurações
];
```

### Variáveis Críticas

**bot.php (linhas 4-21)**
- `BOT_TOKEN` - Token do bot
- `BOT_USERNAME` - Username do bot (sem @)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` - Credenciais MySQL

**force_join.php (linhas 15-21)**
- `FORCE_JOIN_ENABLED` - true/false
- `FORCE_JOIN_CHAT_ID` - ID do canal (-100...)
- `FORCE_JOIN_CHANNEL` - URL do canal

**misticpay/config.php**
- `CLIENT_ID` - ID do MisticPay
- `CLIENT_SECRET` - Secret do MisticPay

---

## 🛠️ SISTEMAS IMPLEMENTADOS

### 1. Sistema VIP

**Arquivos:**
- `vip/users.json` - Usuários VIP
- `vip/payments.json` - Pagamentos

**Planos:**
- 1 semana - R$ 10,00
- 2 semanas - R$ 15,00
- 1 mês - R$ 25,00
- 6 meses - R$ 120,00

**Comandos Admin:**
```
/addvip ID 7d      - Adiciona 7 dias de VIP
/addvip ID 30d     - Adiciona 30 dias de VIP
/rm ID             - Remove VIP
/infovip           - Estatísticas de VIPs
```

### 2. Sistema de Pagamento (PIX)

**Fluxo:**
1. Usuário seleciona plano
2. PIX gerado automaticamente (QR Code)
3. Usuário paga
4. Webhook confirma pagamento
5. VIP ativado automaticamente

**Arquivos:**
- `misticpay/criar_pix.php` - Cria PIX
- `misticpay/check_pix.php` - Verifica status
- `misticpay/webhook.php` - Recebe confirmação

### 3. Antiflood

**4 Camadas de Proteção:**

1. **Por Volume** (security.json)
   - 10 comandos em 60 segundos
   - Ban progressivo: 30s → 60s → 120s

2. **Por Comando Repetido**
   - 3 vezes o mesmo comando em 10 minutos
   - Ativo apenas para `/placa`

3. **Por Consulta Repetida**
   - 1 consulta por placa a cada 5 horas
   - Cache individual por usuário

4. **Ban Manual**
   - Admin pode banir usuários

### 4. Auto-Delete

**Funcionamento:**
- Apenas em grupos (não no privado)
- Apaga comando + resposta após 60 segundos
- Usa fila MySQL para execução via CRON

**Arquivos:**
- `cron_delete.php` - Processa fila

### 5. Force Join

**Funcionamento:**
- Obriga entrada no canal antes de usar comandos
- Apenas no privado (grupos liberados)
- Botão "✅ Já entrei" verifica automaticamente

### 6. Moderação de Grupos

**Recursos:**
- Boas-vindas personalizadas
- Bloqueio de links/mídias
- Anti-spam
- Comandos: `/mute`, `/ban`
- Configuração via `/grupos`

---

## 🧹 MANUTENÇÃO

### Rotação de Logs

**Automático:**
```php
// Verifica a cada 100 escritas no log
// Rotaciona quando > 5 MB
```

**Manual:**
```bash
# Via URL
https://meuvpsbr.shop/log_manager.php?rotate_logs=1

# Via CLI
php log_manager.php
```

**Estatísticas:**
```bash
https://meuvpsbr.shop/log_manager.php?log_stats=1
```

### Limpeza de Security.json

**Automático via Cron:**
```bash
0 3 * * * php /path/to/maintenance.php
```

**Manual:**
```bash
# Via URL
https://meuvpsbr.shop/security_cleaner.php?clean_security=1

# Via CLI
php security_cleaner.php
```

**Remove:**
- Usuários inativos há mais de 30 dias
- Sem ban ativo

### Backup Automático

**Cria backup de:**
- vip/users.json
- vip/payments.json
- data/security.json
- group_admin/data/groups.json
- Configurações

**Manual:**
```bash
# Via URL
https://meuvpsbr.shop/backup_manager.php?backup=1

# Lista backups
https://meuvpsbr.shop/backup_manager.php?backup_list=1
```

**Mantém últimos 10 backups** (remove automaticamente os mais antigos)

### Health Check

**Completo:**
```bash
https://meuvpsbr.shop/health_check.php?health=full
```

**Simples:**
```bash
https://meuvpsbr.shop/health_check.php?health=simple
```

**Verifica:**
- Status do bot
- Conexão com banco
- APIs externas
- Integridade de arquivos
- Recursos do sistema

### Manutenção Completa

**Executa tudo de uma vez:**

```bash
# Via URL (protegido por senha)
https://meuvpsbr.shop/maintenance.php?key=gonzales_maintenance_2026

# Via CLI
php maintenance.php
```

**Realiza:**
1. Rotação de logs
2. Limpeza de security.json
3. Limpeza de pagamentos vencidos
4. Backup automático
5. Otimização do banco
6. Verificação de integridade

---

## 📱 COMANDOS

### Usuário

```
/start ou /menu    - Menu principal
/id                - Informações do usuário
/cpf CPF           - Consulta CPF
/cnpj CNPJ         - Consulta CNPJ
/cep CEP           - Consulta CEP
/nome NOME         - Consulta por nome
/telefone NUMERO   - Consulta telefone
/placa PLACA       - Consulta placa
/ip IP             - Consulta IP
/bin BIN           - Consulta BIN
/checker LOGINS    - Valida logins SISREG
```

### Admin

```
/addvip ID TEMPO   - Adiciona VIP (ex: /addvip 123456 7d)
/rm ID             - Remove VIP
/infovip           - Estatísticas de VIPs
/mute              - Silencia usuário (reply)
/ban               - Bane usuário (reply)
/grupos            - Menu de gerenciamento de grupos
```

---

## 🔌 APIs

### Internas (suas próprias)

```
https://meuvpsbr.shop/apis/serpr00o.php?apikey=gonzales&string=PLACA
https://meuvpsbr.shop/apis/cpf_credilink.php
https://meuvpsbr.shop/apis/telefone_credilink.php
```

### Externas

**Orbyta (Base Local CPF)**
```
URL: https://orbyta.online/api/apifullcpf
Token: z8EY1omtgO0NQRZEO26TayS5iCx1zlMq
```

**MisticPay (Pagamentos)**
```
URL: https://api.misticpay.com
CLIENT_ID: ci_zq3kz1dq09ka5mg
CLIENT_SECRET: cs_t6tawn7spcu63md8fda5rcpwy
```

**ViaCEP**
```
URL: https://viacep.com.br/ws/{CEP}/json/
```

---

## 🔒 SEGURANÇA

### Proteções Implementadas

1. **Validação de entrada**
   - Todos os comandos validam formato
   - Sanitização de dados

2. **Antiflood robusto**
   - 4 camadas de proteção
   - Ban progressivo

3. **Logs seguros**
   - Sem exposição de tokens
   - Rotação automática

4. **Arquivos JSON**
   - Lock de arquivo ao escrever
   - Backup antes de limpar

5. **Senhas e tokens**
   - Nunca no git
   - Centralizados em config

### Recomendações

1. **Troque as senhas padrão**
   - Senha do maintenance.php
   - Credenciais do banco

2. **Proteja o painel admin**
   - Renomeie admin.html para algo único
   - Adicione .htaccess com senha

3. **Monitore logs**
   - Verifique bot.log diariamente
   - Use health check

4. **Backup regular**
   - Configure CRON diário
   - Baixe backups semanalmente

---

## 📊 PAINEL ADMINISTRATIVO

**Acesse:**
```
https://meuvpsbr.shop/admin.html
```

**Funcionalidades:**
- ✅ Status do sistema em tempo real
- ✅ Estatísticas de logs
- ✅ Gerenciamento de segurança
- ✅ Criação de backups
- ✅ Execução de manutenção
- ✅ Auto-refresh a cada 30 segundos

---

## 🐛 TROUBLESHOOTING

### Bot não responde

1. Verifique webhook:
```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

2. Verifique logs:
```bash
tail -f bot.log
```

3. Teste health check:
```bash
https://meuvpsbr.shop/health_check.php?health=full
```

### Banco desconectado

1. Verifique credenciais em `bot.php` (linhas 15-20)
2. Teste conexão:
```bash
mysql -h localhost -u u937550989_cron_delete -p
```

### PIX não funciona

1. Verifique credenciais MisticPay
2. Teste API:
```bash
curl https://api.misticpay.com
```

### Logs muito grandes

1. Execute rotação manual:
```bash
php log_manager.php
```

2. Configure CRON para manutenção diária

---

## 📝 CHANGELOG

### v2.0 - Otimizações Profissionais (2026-02-09)

**Corrigido:**
- ✅ Erro de mkdir recorrente
- ✅ Logs crescendo indefinidamente
- ✅ security.json muito grande

**Adicionado:**
- ✅ Sistema de rotação de logs automático
- ✅ Limpeza de security.json (usuários inativos)
- ✅ Sistema de backup automático
- ✅ Health check completo
- ✅ Painel administrativo web
- ✅ Manutenção automatizada
- ✅ Arquivo de configuração centralizado

**Melhorado:**
- ✅ Performance geral
- ✅ Tratamento de erros
- ✅ Segurança
- ✅ Documentação

---

## 📞 SUPORTE

- **Canal:** https://t.me/GonzalesCanal
- **Suporte:** https://t.me/GonzalesDev
- **Bot:** @EmonNullbot

---

## 📄 LICENÇA

Todos os direitos reservados © 2026

---

**Desenvolvido por Gonzales ⚡**
