<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scopes', fn (Blueprint $table) => $table->string('code', 12)->change());
        Schema::create('abroad_cities', function (Blueprint $table) {
            $table->id();
            $table->string('tse_code', 5)->unique();
            $table->foreignId('scope_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('country_code', 3)->index();
            $table->string('country_name');
            $table->json('geographic_evidence');
            $table->char('reference_sha256', 64);
        });
        Schema::create('abroad_round_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('abroad_city_id')->constrained()->restrictOnDelete();
            $table->char('catalog_sha256', 64);
            $table->unique(['election_round_id', 'abroad_city_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abroad_round_cities');
        Schema::dropIfExists('abroad_cities');
        // Keep the widened code column: stored snapshots must remain readable.
    }
};
