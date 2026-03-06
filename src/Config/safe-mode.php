<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safe Mode Habilitado
    |--------------------------------------------------------------------------
    |
    | Quando true (padrão), comandos bloqueados são impedidos de executar
    | em um servidor com banco de dados remoto.
    |
    | Quando false, o comando NÃO é bloqueado, mas um registro de auditoria
    | completo é salvo no banco de auditoria configurado.
    |
    | Defina na .env:  SAFE_MODE=false
    |
    */
    'enabled' => env('SAFE_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Forçar Safe Mode em Produção
    |--------------------------------------------------------------------------
    |
    | Se true, quando APP_ENV=production o safe mode é sempre habilitado,
    | independente do valor de SAFE_MODE na .env.
    |
    */
    'force_on_production' => env('SAFE_MODE_FORCE_PRODUCTION', true),

    /*
    |--------------------------------------------------------------------------
    | Comandos Bloqueados
    |--------------------------------------------------------------------------
    |
    | Lista de comandos Artisan que serão verificados/bloqueados quando o
    | banco de dados default estiver em um servidor remoto.
    |
    */
    'blocked_commands' => [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'down',
    ],

    /*
    |--------------------------------------------------------------------------
    | Conexão de Auditoria
    |--------------------------------------------------------------------------
    |
    | A conexão de banco de dados onde os logs de auditoria serão salvos.
    | Configurada durante a instalação via: php artisan safe-mode:install
    |
    | Defina na .env:  SAFE_MODE_AUDIT_CONNECTION=mysql
    |
    */
    'audit_connection' => env('SAFE_MODE_AUDIT_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | IPs / Hosts Locais Permitidos
    |--------------------------------------------------------------------------
    |
    | Além dos IPs locais padrão, você pode adicionar aqui outros hosts ou
    | CIDRs que devem ser considerados locais/seguros.
    |
    | Defina na .env (separados por vírgula):
    |   SAFE_MODE_ALLOWED_IPS=10.0.0.10,192.168.0.1
    |
    */
    'allowed_ips' => array_filter(
        explode(',', env('SAFE_MODE_ALLOWED_IPS', ''))
    ),

    /*
    |--------------------------------------------------------------------------
    | Notificações via Webhook
    |--------------------------------------------------------------------------
    |
    | Quando configurado, SafeMode envia uma notificação ao webhook sempre
    | que um comando bloqueado ou auditado for executado.
    |
    | Canais suportados: slack | discord | generic
    |
    */
    'webhook' => [
        'enabled' => env('SAFE_MODE_WEBHOOK_ENABLED', false),
        'channel' => env('SAFE_MODE_WEBHOOK_CHANNEL', 'generic'), // slack | discord | generic
        'url'     => env('SAFE_MODE_WEBHOOK_URL'),
    ],

];
