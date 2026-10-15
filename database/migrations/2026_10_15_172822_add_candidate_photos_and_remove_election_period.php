<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', fn (Blueprint $table) => $table->string('photo_path')->nullable());
        Schema::table('elections', fn (Blueprint $table) => $table->dropColumn('period_label'));
    }

    public function down(): void
    {
        Schema::table('candidates', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::table('elections', fn (Blueprint $table) => $table->string('period_label')->default(''));
    }
};
