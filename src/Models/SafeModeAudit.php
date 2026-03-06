<?php

namespace Allyson\SafeMode\Models;

use Illuminate\Database\Eloquent\Model;

class SafeModeAudit extends Model
{
    /**
     * A tabela associada ao model.
     *
     * @var string
     */
    protected $table = 'safe_mode_audits';

    /**
     * Campos que podem ser preenchidos em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'command',
        'user',
        'machine',
        'ip',
        'database_host',
        'connection',
        'app_env',
        'output',
        'exit_code',
        'blocked',
    ];

    /**
     * Casts de tipos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'blocked'   => 'boolean',
        'exit_code' => 'integer',
    ];

    /**
     * Apenas created_at — registros de auditoria são imutáveis.
     */
    public const UPDATED_AT = null;

    /**
     * Retorna a conexão de banco configurada para auditoria,
     * sobreescrevendo o default do app.
     */
    public function getConnectionName(): ?string
    {
        return config('safe-mode.audit_connection') ?? parent::getConnectionName();
    }
}
