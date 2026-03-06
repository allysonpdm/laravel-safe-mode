<?php

namespace Allyson\SafeMode\Services;

use Allyson\SafeMode\Models\SafeModeAudit;
use Illuminate\Support\Facades\Http;

class WebhookNotifier
{
    /**
     * Envia notificação para o webhook configurado.
     */
    public function send(SafeModeAudit $audit, bool $blocked = false): void
    {
        $url = config('safe-mode.webhook.url');

        if (empty($url)) {
            return;
        }

        $channel = config('safe-mode.webhook.channel', 'generic');
        $payload = $this->buildPayload($audit, $blocked, $channel);

        try {
            Http::timeout(5)->post($url, $payload);
        } catch (\Throwable) {
            // Silencia falhas de webhook para não impedir o fluxo principal
        }
    }

    private function buildPayload(SafeModeAudit $audit, bool $blocked, string $channel): array
    {
        $status = $blocked ? '🚨 BLOQUEADO' : '⚠️ AUDITADO';
        $text   = "[SafeMode] {$status} — `{$audit->command}`\n" .
                  "Usuário: {$audit->user} | Máquina: {$audit->machine} | IP: {$audit->ip}\n" .
                  "Host DB: {$audit->database_host} | Env: {$audit->app_env}\n" .
                  "Em: {$audit->created_at}";

        return match ($channel) {
            'slack'   => ['text' => $text],
            'discord' => ['content' => $text],
            default   => [
                'status'        => $blocked ? 'blocked' : 'audited',
                'command'       => $audit->command,
                'user'          => $audit->user,
                'machine'       => $audit->machine,
                'ip'            => $audit->ip,
                'database_host' => $audit->database_host,
                'app_env'       => $audit->app_env,
                'created_at'    => (string) $audit->created_at,
            ],
        };
    }
}
