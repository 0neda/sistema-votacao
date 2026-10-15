<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('votes')->exists()) {
            throw new RuntimeException('Esta migração exige remover os votos identificados de teste antes de continuar. Nenhum voto existente foi alterado.');
        }
        Schema::drop('votes');
        Schema::create('votes', function (Blueprint $table): void {
            $table->char('id', 64)->primary();
            $table->foreignId('round_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->boolean('is_simulated')->default(false);
            $table->foreign(['round_id', 'candidate_id'])->references(['round_id', 'candidate_id'])->on('round_candidates')->restrictOnDelete();
        });
        Schema::create('participations', function (Blueprint $table): void {
            $table->foreignId('round_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['round_id', 'user_id']);
        });
        foreach (['votes', 'participations'] as $table) {
            DB::statement("CREATE TRIGGER immutable_{$table} BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_immutable_changes()");
        }
    }

    public function down(): void
    {
        Schema::drop('participations');
        Schema::drop('votes');
        Schema::create('votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['round_id', 'user_id']);
            $table->foreign(['round_id', 'candidate_id'])->references(['round_id', 'candidate_id'])->on('round_candidates')->restrictOnDelete();
        });
        DB::statement('CREATE TRIGGER immutable_votes BEFORE UPDATE OR DELETE ON votes FOR EACH ROW EXECUTE FUNCTION prevent_immutable_changes()');
    }
};
