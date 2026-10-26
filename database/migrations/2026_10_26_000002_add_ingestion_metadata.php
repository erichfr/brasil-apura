<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->timestamp('source_generated_at', 6)->nullable();
            $table->boolean('votes_disclosed')->default(false);
            $table->json('official_metadata')->nullable();
        });
        Schema::table('sync_runs', function (Blueprint $table) {
            $table->char('response_sha256', 64)->nullable();
            $table->string('raw_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_runs', fn (Blueprint $table) => $table->dropColumn(['response_sha256', 'raw_path']));
        Schema::table('snapshots', fn (Blueprint $table) => $table->dropColumn(['source_generated_at', 'votes_disclosed', 'official_metadata']));
    }
};
