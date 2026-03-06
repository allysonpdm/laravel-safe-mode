<?php

namespace Allyson\SafeMode\Console;

use Allyson\SafeMode\Services\ConnectionInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallCommand extends Command
{
    protected $signature   = 'safe-mode:install';
    protected $description = 'Instala e configura o SafeMode no projeto Laravel';

    public function handle(ConnectionInspector $inspector): int
    {
        $this->components->info('SafeMode — Assistente de Instalação');
        $this->newLine();

        // 1. Publicar config
        $this->components->task('Publicando arquivo de configuração', function () {
            Artisan::call('vendor:publish', [
                '--tag'   => 'safe-mode-config',
                '--force' => false,
            ]);
        });

        // 2. Publicar migrations
        $this->components->task('Publicando migrations', function () {
            Artisan::call('vendor:publish', [
                '--tag'   => 'safe-mode-migrations',
                '--force' => false,
            ]);
        });

        $this->newLine();

        // 3. Selecionar conexão de auditoria
        $connections = $this->getAvailableConnections();

        if (empty($connections)) {
            $this->components->error('Nenhuma conexão de banco de dados configurada em config/database.php.');
            return self::FAILURE;
        }

        $this->components->info('Selecione a conexão que será usada para registrar as auditorias:');

        $auditConnection = $this->choice(
            'Conexão de auditoria',
            $connections,
            $this->guessDefaultAuditConnection($connections)
        );

        // 4. Testar conexão escolhida
        $this->components->task("Verificando conexão [{$auditConnection}]", function () use ($auditConnection, &$connectionOk) {
            try {
                DB::connection($auditConnection)->getPdo();
                $connectionOk = true;
            } catch (\Throwable $e) {
                $connectionOk = false;
                $this->connectionError = $e->getMessage();
            }
        });

        if (!($connectionOk ?? false)) {
            $this->newLine();
            $this->components->error("Não foi possível conectar ao banco [{$auditConnection}]: " . ($this->connectionError ?? ''));
            $this->components->warn('Configure a conexão corretamente e execute safe-mode:install novamente.');
            return self::FAILURE;
        }

        // 5. Salvar na .env
        $this->setEnvValue('SAFE_MODE_AUDIT_CONNECTION', $auditConnection);

        // 6. Perguntar SAFE_MODE padrão
        $safeModeDefault = $this->confirm(
            'Habilitar bloqueio de comandos por padrão (SAFE_MODE=true)?',
            true
        );
        $this->setEnvValue('SAFE_MODE', $safeModeDefault ? 'true' : 'false');

        // 7. Perguntar sobre webhook
        if ($this->confirm('Deseja configurar notificações via webhook? (Slack/Discord/HTTP)')) {
            $this->configureWebhook();
        }

        // 8. Executar migrations
        $this->newLine();
        if ($this->confirm('Executar as migrations de auditoria agora?', true)) {
            $this->components->task('Executando migrations', function () {
                Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true]);
            });
        }

        $this->newLine();
        $this->components->success('SafeMode instalado com sucesso!');
        $this->newLine();

        $this->line('  <fg=yellow>Resumo da configuração:</>');
        $this->line("  SAFE_MODE                    = " . ($safeModeDefault ? 'true' : 'false'));
        $this->line("  SAFE_MODE_AUDIT_CONNECTION   = {$auditConnection}");
        $this->newLine();
        $this->line('  <fg=cyan>Comandos protegidos (padrão):</>');
        $blocked = config('safe-mode.blocked_commands', []);
        foreach ($blocked as $cmd) {
            $this->line("  • php artisan {$cmd}");
        }

        return self::SUCCESS;
    }

    /**
     * Lista as conexões definidas em config/database.php.
     */
    private function getAvailableConnections(): array
    {
        $connections = array_keys(config('database.connections', []));

        return array_values(array_filter($connections, fn ($c) => !empty(config("database.connections.{$c}"))));
    }

    /**
     * Tenta adivinhar qual conexão faz mais sentido como auditoria (não a default).
     */
    private function guessDefaultAuditConnection(array $connections): int
    {
        $default = config('database.default');

        // Prefere uma conexão diferente da default para separar audit do app
        $nonDefault = array_values(array_filter($connections, fn ($c) => $c !== $default));

        if (!empty($nonDefault)) {
            return (int) array_search($nonDefault[0], $connections);
        }

        return (int) array_search($default, $connections);
    }

    /**
     * Define ou atualiza uma variável no arquivo .env.
     */
    private function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        // Saneamento: remove aspas simples/duplas do valor
        $safeValue = preg_replace('/[\'"]/', '', $value);

        // Se a chave já existe, substitui
        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$safeValue}", $content);
        } else {
            $content .= PHP_EOL . "{$key}={$safeValue}";
        }

        file_put_contents($envPath, $content);
    }

    /**
     * Assistente de configuração de webhook.
     */
    private function configureWebhook(): void
    {
        $channel = $this->choice(
            'Canal do webhook',
            ['slack', 'discord', 'generic'],
            2
        );

        $url = $this->ask('URL do webhook');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->components->warn('URL inválida. Configuração de webhook ignorada.');
            return;
        }

        $this->setEnvValue('SAFE_MODE_WEBHOOK_ENABLED', 'true');
        $this->setEnvValue('SAFE_MODE_WEBHOOK_CHANNEL', $channel);
        $this->setEnvValue('SAFE_MODE_WEBHOOK_URL', $url);

        $this->components->info("Webhook {$channel} configurado.");
    }
}
