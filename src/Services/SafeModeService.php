<?php

namespace Allyson\SafeMode\Services;

use Allyson\SafeMode\Exceptions\AuditConnectionException;
use Allyson\SafeMode\Exceptions\UnsafeCommandException;
use Illuminate\Console\Events\CommandStarting;

class SafeModeService
{
    public function __construct(
        private readonly ConnectionInspector $inspector,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Ponto de entrada principal — chamado quando um comando Artisan inicia.
     */
    public function handle(CommandStarting $event): void
    {
        $command = $event->command ?? '';

        if (!$this->isBlockedCommand($command)) {
            return;
        }

        // Forçar safe mode em produção, independente de .env
        $safeModeEnabled = $this->isSafeModeEnabled();

        // Conexão local sempre permitida, com ou sem safe mode
        if ($this->inspector->isLocalConnection()) {
            return;
        }

        // Conexão remota detectada — verficiar disponibilidade do banco de auditoria
        try {
            $this->inspector->assertAuditConnectionAvailable();
        } catch (\RuntimeException $e) {
            // Sem banco de auditoria configurado: bloqueio incondicional por segurança
            throw new AuditConnectionException($e->getMessage());
        }

        if ($safeModeEnabled) {
            // Registrar tentativa bloqueada e lançar exceção
            $this->auditService->registerBlocked($command);

            throw new UnsafeCommandException(
                "SafeMode bloqueou o comando [{$command}] pois a conexão de banco de dados é remota.\n" .
                "Host: {$this->inspector->getCurrentHost()}\n" .
                "Para desabilitar o bloqueio, defina SAFE_MODE=false na .env " .
                "(o comando será auditado mas não impedido)."
            );
        }

        // SAFE_MODE=false → registra auditoria e permite prosseguir
        $this->auditService->register($command);
    }

    /**
     * Verifica se o safe mode está habilitado, levando em conta o ambiente.
     */
    private function isSafeModeEnabled(): bool
    {
        if (config('safe-mode.force_on_production') && app()->environment('production')) {
            return true;
        }

        return (bool) config('safe-mode.enabled', true);
    }

    /**
     * Verifica se o comando está na lista de comandos bloqueados.
     */
    private function isBlockedCommand(string $command): bool
    {
        $blocked = (array) config('safe-mode.blocked_commands', []);

        // Normaliza removendo argumentos extras (ex: "migrate:fresh --seed" → "migrate:fresh")
        $normalized = strtolower(trim(explode(' ', $command)[0]));

        foreach ($blocked as $item) {
            if (strtolower(trim($item)) === $normalized) {
                return true;
            }
        }

        return false;
    }
}
