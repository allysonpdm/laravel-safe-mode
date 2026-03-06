<?php

namespace Allyson\SafeMode\Services;

use Allyson\SafeMode\Models\SafeModeAudit;
use Allyson\SafeMode\Services\WebhookNotifier;

class AuditService
{
    public function __construct(
        private readonly WebhookNotifier $webhookNotifier
    ) {}

    /**
     * Registra a execução auditada de um comando no banco de auditoria.
     */
    public function register(string $command, ?string $output = null, ?int $exitCode = null): SafeModeAudit
    {
        $connection = config('database.default');

        $record = SafeModeAudit::on(config('safe-mode.audit_connection'))->create([
            'command'       => $command,
            'user'          => get_current_user() ?: 'unknown',
            'machine'       => gethostname() ?: 'unknown',
            'ip'            => $this->resolveCurrentIp(),
            'database_host' => config("database.connections.{$connection}.host", 'unknown'),
            'connection'    => $connection,
            'app_env'       => app()->environment(),
            'output'        => $output,
            'exit_code'     => $exitCode,
        ]);

        // Disparar webhook se habilitado
        if (config('safe-mode.webhook.enabled')) {
            $this->webhookNotifier->send($record, blocked: false);
        }

        return $record;
    }

    /**
     * Registra uma tentativa bloqueada de executar um comando.
     */
    public function registerBlocked(string $command): SafeModeAudit
    {
        $connection = config('database.default');

        $record = SafeModeAudit::on(config('safe-mode.audit_connection'))->create([
            'command'       => $command,
            'user'          => get_current_user() ?: 'unknown',
            'machine'       => gethostname() ?: 'unknown',
            'ip'            => $this->resolveCurrentIp(),
            'database_host' => config("database.connections.{$connection}.host", 'unknown'),
            'connection'    => $connection,
            'app_env'       => app()->environment(),
            'output'        => null,
            'exit_code'     => null,
            'blocked'       => true,
        ]);

        // Disparar webhook se habilitado
        if (config('safe-mode.webhook.enabled')) {
            $this->webhookNotifier->send($record, blocked: true);
        }

        return $record;
    }

    private function resolveCurrentIp(): string
    {
        $hostname = gethostname();
        if (!$hostname) {
            return 'unknown';
        }

        $ip = gethostbyname($hostname);

        return ($ip !== $hostname) ? $ip : 'unknown';
    }
}
