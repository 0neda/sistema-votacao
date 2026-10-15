<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('eleitor');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('period_label');
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description');
            $table->unsignedSmallInteger('display_order');
            $table->unique(['election_id', 'name']);
        });
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->enum('status', ['agendado', 'aberto', 'encerrado']);
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->text('selection_justification')->nullable();
            $table->timestampsTz();
            $table->unique(['election_id', 'number']);
            $table->index(['status', 'scheduled_at', 'id']);
        });
        Schema::create('round_candidates', function (Blueprint $table) {
            $table->foreignId('round_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->primary(['round_id', 'candidate_id']);
        });
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['round_id', 'user_id']);
            $table->foreign(['round_id', 'candidate_id'])->references(['round_id', 'candidate_id'])->on('round_candidates')->restrictOnDelete();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event')->index();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('result');
            $table->ipAddress('ip_address')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });
        DB::statement("CREATE UNIQUE INDEX idx_single_open_round ON rounds ((1)) WHERE status = 'aberto'");
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_immutable_changes() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'Registros de votos e auditoria sao imutaveis';
                END;
                $$;
                CREATE TRIGGER immutable_votes BEFORE UPDATE OR DELETE ON votes
                FOR EACH ROW EXECUTE FUNCTION prevent_immutable_changes();
                CREATE TRIGGER immutable_audit_logs BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_immutable_changes();
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['audit_logs', 'votes', 'round_candidates', 'rounds', 'candidates', 'elections'] as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_immutable_changes()');
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
    }
};
