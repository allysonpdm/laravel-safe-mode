# allyson/laravel-safe-mode

[![PHP](https://img.shields.io/badge/PHP-8.5%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

> Protege ambientes Laravel contra execução acidental de comandos Artisan destrutivos em produção — com auditoria completa, notificações e whitelist de IPs.

---

## O problema que essa lib resolve

```bash
# Rodado em produção por engano → dados PERDIDOS
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
```

Isso já aconteceu em empresas grandes. O SafeMode impede.

---

## Como funciona

```
User roda: php artisan migrate:fresh
                │
                ▼
      SafeModeService.handle()
                │
                ├── Comando não está na lista? → Passa livre
                │
                ▼
      Verifica APP_ENV + SAFE_MODE
                │
                ▼
      Verifica host do banco default
                │
         ┌──────┴──────────────────┐
         │                         │
     Host LOCAL                Host REMOTO
     (localhost, 127.x, 192.168.x)   │
         │                         ▼
         │               SAFE_MODE=true (padrão)
         │               ┌─────────────────────┐
         └──────────┐    │  Registra tentativa  │
                    │    │  BLOQUEADA no audit  │
                    ▼    │  Lança exceção        │
                 Permite └─────────────────────┘
                 executar
                            SAFE_MODE=false
                         ┌─────────────────────┐
                         │  Registra auditoria  │
                         │  Permite executar    │
                         └─────────────────────┘
```

> Em ambos os casos com host remoto, o banco de auditoria **precisa estar disponível**. Se não estiver, o comando é **bloqueado incondicionalmente**.

---

## Instalação

```bash
composer require allyson/laravel-safe-mode
```

O Service Provider é registrado automaticamente via [Laravel Package Auto-Discovery](https://laravel.com/docs/packages#package-discovery).

### Configuração inicial

```bash
php artisan safe-mode:install
```

O assistente irá:
1. Publicar `config/safe-mode.php`
2. Publicar as migrations
3. Perguntar **qual conexão** usará para salvar as auditorias
4. Atualizar o `.env` automaticamente
5. Executar as migrations (opcional)

---

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `SAFE_MODE` | `true` | `true` = bloqueia em servidor remoto \| `false` = audita sem bloquear |
| `SAFE_MODE_AUDIT_CONNECTION` | _(vazio)_ | Nome da conexão do banco de auditoria |
| `SAFE_MODE_FORCE_PRODUCTION` | `true` | Força safe mode quando `APP_ENV=production` |
| `SAFE_MODE_ALLOWED_IPS` | _(vazio)_ | IPs adicionais considerados locais (separados por vírgula) |
| `SAFE_MODE_WEBHOOK_ENABLED` | `false` | Habilita notificações via webhook |
| `SAFE_MODE_WEBHOOK_CHANNEL` | `generic` | Canal: `slack`, `discord` ou `generic` |
| `SAFE_MODE_WEBHOOK_URL` | _(vazio)_ | URL do webhook |

Exemplo de `.env`:

```dotenv
SAFE_MODE=true
SAFE_MODE_AUDIT_CONNECTION=mysql_audit
SAFE_MODE_FORCE_PRODUCTION=true
SAFE_MODE_ALLOWED_IPS=10.0.0.10,192.168.1.50

# Webhook opcional
SAFE_MODE_WEBHOOK_ENABLED=true
SAFE_MODE_WEBHOOK_CHANNEL=slack
SAFE_MODE_WEBHOOK_URL=https://hooks.slack.com/services/xxx/yyy/zzz
```

---

## Configuração (`config/safe-mode.php`)

```php
return [
    'enabled'              => env('SAFE_MODE', true),
    'force_on_production'  => env('SAFE_MODE_FORCE_PRODUCTION', true),

    'blocked_commands' => [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'down',
    ],

    'audit_connection' => env('SAFE_MODE_AUDIT_CONNECTION'),

    'allowed_ips' => array_filter(explode(',', env('SAFE_MODE_ALLOWED_IPS', ''))),

    'webhook' => [
        'enabled' => env('SAFE_MODE_WEBHOOK_ENABLED', false),
        'channel' => env('SAFE_MODE_WEBHOOK_CHANNEL', 'generic'),
        'url'     => env('SAFE_MODE_WEBHOOK_URL'),
    ],
];
```

---

## Tabela de auditoria (`safe_mode_audits`)

| Campo | Tipo | Descrição |
|---|---|---|
| `id` | bigint | Chave primária |
| `command` | string | Comando executado (ex: `migrate:fresh`) |
| `user` | string | Usuário do SO (PHP process) |
| `machine` | string | Hostname da máquina |
| `ip` | string | IP da máquina |
| `database_host` | string | Host do banco alvo |
| `connection` | string | Nome da conexão Laravel |
| `app_env` | string | `APP_ENV` no momento |
| `output` | longtext | Saída do comando (quando capturada) |
| `exit_code` | integer | Código de saída |
| `blocked` | boolean | `true` = bloqueado, `false` = auditado |
| `created_at` | timestamp | Data/hora do registro |

---

## Detecção de host local

O `LocalIpDetector` considera local:

- `127.0.0.1`, `localhost`, `::1`, `0.0.0.0`
- Redes `10.x.x.x` (RFC 1918)
- Redes `192.168.x.x` (RFC 1918)
- Redes `172.16.x.x` a `172.31.x.x` (RFC 1918)
- `169.254.x.x` (link-local)
- Hostnames que **resolvem** para um dos IPs acima
- IPs adicionais configurados em `SAFE_MODE_ALLOWED_IPS`
- Conexões SQLite (sempre locais)

---

## Adicionar comandos à lista de proteção

```php
// config/safe-mode.php
'blocked_commands' => [
    'migrate:fresh',
    'migrate:refresh',
    'migrate:reset',
    'db:wipe',
    'down',
    // adicione seus próprios:
    'meu-comando:perigoso',
],
```

---

## Notificações via Webhook

### Slack

```dotenv
SAFE_MODE_WEBHOOK_CHANNEL=slack
SAFE_MODE_WEBHOOK_URL=https://hooks.slack.com/services/T00/B00/xxx
```

### Discord

```dotenv
SAFE_MODE_WEBHOOK_CHANNEL=discord
SAFE_MODE_WEBHOOK_URL=https://discord.com/api/webhooks/xxx/yyy
```

### HTTP Genérico (JSON)

```dotenv
SAFE_MODE_WEBHOOK_CHANNEL=generic
SAFE_MODE_WEBHOOK_URL=https://meu-sistema.com/webhook/safe-mode
```

Payload enviado:

```json
{
  "status": "blocked",
  "command": "migrate:fresh",
  "user": "deploy",
  "machine": "prod-web-01",
  "ip": "10.0.0.5",
  "database_host": "rds.amazonaws.com",
  "app_env": "production",
  "created_at": "2026-03-06 14:32:00"
}
```

---

## Estrutura do pacote

```
laravel-safe-mode/
├── src/
│   ├── SafeModeServiceProvider.php
│   ├── Config/
│   │   └── safe-mode.php
│   ├── Console/
│   │   └── InstallCommand.php
│   ├── Exceptions/
│   │   ├── UnsafeCommandException.php
│   │   └── AuditConnectionException.php
│   ├── Models/
│   │   └── SafeModeAudit.php
│   ├── Services/
│   │   ├── SafeModeService.php
│   │   ├── ConnectionInspector.php
│   │   ├── AuditService.php
│   │   └── WebhookNotifier.php
│   └── Support/
│       └── LocalIpDetector.php
├── database/
│   └── migrations/
│       └── 2025_01_01_000000_create_safe_mode_audits_table.php
├── composer.json
└── README.md
```

---

## Requisitos

- PHP **8.5+**
- Laravel **12.x**

---

## Licença

MIT — veja [LICENSE](LICENSE).


Estrutura final

```code
laravel-safe-mode/
├── composer.json
├── README.md
├── database/migrations/
│   └── 2025_01_01_000000_create_safe_mode_audits_table.php
└── src/
    ├── SafeModeServiceProvider.php
    ├── Config/safe-mode.php
    ├── Console/InstallCommand.php
    ├── Exceptions/
    │   ├── UnsafeCommandException.php
    │   └── AuditConnectionException.php
    ├── Models/SafeModeAudit.php
    ├── Services/
    │   ├── SafeModeService.php        ← orquestrador principal
    │   ├── ConnectionInspector.php    ← detecta se host é local/remoto
    │   ├── AuditService.php           ← salva registros + dispara webhook
    │   └── WebhookNotifier.php        ← Slack / Discord / HTTP genérico
    └── Support/LocalIpDetector.php    ← RFC 1918 + resolução de hostnames
```

---

## Decisões de design implementadas

| Requisito                          | Implementação                                                                 |
|-----------------------------------|------------------------------------------------------------------------------|
| Bloquear migrate:fresh etc. em host remoto | SafeModeService via CommandStarting event                                    |
| SAFE_MODE=false audita sem bloquear         | Fluxo no SafeModeService.handle()                                           |
| Banco de auditoria obrigatório             | ConnectionInspector.assertAuditConnectionAvailable() — sem banco, bloqueia sempre |
| safe-mode:install interativo               | InstallCommand com seleção de conexão, testes, atualização do .env         |
| Forçar safe mode em APP_ENV=production     | force_on_production na config                                               |
| Whitelist de IPs extras                    | SAFE_MODE_ALLOWED_IPS + LocalIpDetector.isWhitelisted()                    |
| Notificações webhook                       | WebhookNotifier para Slack, Discord e HTTP genérico                        |
| SQLite sempre local                        | ConnectionInspector verifica driver antes do host                           |
| blocked vs audited separados               | Campo blocked boolean na tabela + método registerBlocked()                 |