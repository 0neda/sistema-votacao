<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->timestampTz('completed_at')->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX idx_single_active_election ON elections ((1)) WHERE completed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_single_active_election');
        Schema::table('elections', fn (Blueprint $table) => $table->dropColumn('completed_at'));
    }
};
