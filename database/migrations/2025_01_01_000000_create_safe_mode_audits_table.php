<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retorna a conexão definida para auditoria, se houver.
     */
    public function getConnection(): ?string
    {
        return config('safe-mode.audit_connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('safe_mode_audits', function (Blueprint $table) {
            $table->id();

            // Comando executado (ex: "migrate:fresh")
            $table->string('command');

            // Usuário do SO que rodou o processo PHP
            $table->string('user')->nullable();

            // Hostname da máquina que executou
            $table->string('machine')->nullable();

            // IP resolvido da máquina
            $table->string('ip', 45)->nullable(); // 45 chars suporta IPv6

            // Host do banco de dados alvo da operação
            $table->string('database_host')->nullable();

            // Nome da conexão de banco usada (ex: pgsql, mysql)
            $table->string('connection')->nullable();

            // APP_ENV no momento da execução
            $table->string('app_env', 50)->nullable();

            // Saída capturada do comando (quando disponível)
            $table->longText('output')->nullable();

            // Código de saída do processo
            $table->integer('exit_code')->nullable();

            // Indica se o comando foi bloqueado (true) ou apenas auditado (false)
            $table->boolean('blocked')->default(false);

            // Apenas data de criação — registros são imutáveis
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('safe_mode_audits');
    }
};
