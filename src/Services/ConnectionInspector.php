<?php

namespace Allyson\SafeMode\Services;

use Allyson\SafeMode\Exceptions\AuditConnectionException;
use Allyson\SafeMode\Support\LocalIpDetector;
use Illuminate\Support\Facades\DB;

class ConnectionInspector
{
    /**
     * Verifica se a conexão padrão do banco de dados aponta para um host local/privado.
     */
    public function isLocalConnection(?string $connection = null): bool
    {
        $connection ??= config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        // SQLite é sempre considerado local
        if ($driver === 'sqlite') {
            return true;
        }

        $host = config("database.connections.{$connection}.host", '');

        $allowedIps = (array) config('safe-mode.allowed_ips', []);

        if (LocalIpDetector::isWhitelisted($host, $allowedIps)) {
            return true;
        }

        return LocalIpDetector::isLocal($host);
    }

    /**
     * Testa se a conexão de auditoria está disponível agora.
     *
     * @throws \RuntimeException
     */
    public function assertAuditConnectionAvailable(): void
    {
        $auditConnection = config('safe-mode.audit_connection');

        if (empty($auditConnection)) {
            throw new AuditConnectionException(
                '[SafeMode] Conexão de auditoria não configurada. ' .
                'Execute: php artisan safe-mode:install'
            );
        }

        // Validação: a conexão de auditoria NÃO pode ser remota (por segurança)
        if (!$this->isLocalConnection($auditConnection)) {
            throw new AuditConnectionException(
                "[SafeMode] A conexão de auditoria '{$auditConnection}' aponta para um servidor remoto. " .
                "Configure SAFE_MODE_AUDIT_CONNECTION para uma conexão local (ex: pgsql_logs) em .env"
            );
        }

        try {
            DB::connection($auditConnection)->getPdo();
        } catch (\Throwable $e) {
            throw new AuditConnectionException(
                "[SafeMode] Banco de auditoria ({$auditConnection}) indisponível: " . $e->getMessage()
            );
        }
    }

    /**
     * Retorna o host da conexão padrão.
     */
    public function getCurrentHost(?string $connection = null): string
    {
        $connection ??= config('database.default');

        return (string) config("database.connections.{$connection}.host", 'unknown');
    }
}
