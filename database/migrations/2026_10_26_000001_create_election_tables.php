<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name');
            $table->string('office_code', 4)->default('0001');
            $table->timestamps();
        });
        Schema::create('election_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('election_date');
            $table->string('tse_election_code', 12)->unique();
            $table->string('tse_contest_code', 12);
            $table->timestamps();
            $table->unique(['election_id', 'number']);
        });
        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name');
            $table->string('type', 16);
        });
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->restrictOnDelete();
            $table->string('tse_candidate_id', 32);
            $table->string('ballot_name');
            $table->string('ballot_number', 8);
            $table->string('party')->nullable();
            $table->string('federation')->nullable();
            $table->text('official_photo_url')->nullable();
            $table->timestamps();
            $table->unique(['election_id', 'tse_candidate_id']);
        });
        Schema::create('round_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->unique(['election_round_id', 'candidate_id']);
        });
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('scope_id')->constrained()->restrictOnDelete();
            $table->string('file_type', 16);
            $table->text('url');
            $table->string('schema_version')->nullable();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->timestamp('last_success_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['election_round_id', 'scope_id', 'file_type']);
        });
        Schema::create('source_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->char('sha256', 64);
            $table->string('generation_id')->nullable();
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->timestamp('source_reported_at', 6)->nullable();
            $table->timestamp('collected_at', 6);
            $table->unique(['source_id', 'sha256']);
        });
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_payload_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('started_at', 6);
            $table->timestamp('finished_at', 6)->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->index(['source_id', 'started_at']);
        });
        Schema::create('snapshot_imports', function (Blueprint $table) {
            $table->id();
            $table->text('origin');
            $table->text('verification_notes');
            $table->char('manifest_sha256', 64)->unique();
            $table->timestamp('imported_at', 6);
        });
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_payload_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('snapshot_import_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('source_reported_at', 6)->nullable();
            $table->timestamp('collected_at', 6);
            $table->unsignedBigInteger('total_votes')->nullable();
            $table->unsignedBigInteger('valid_votes')->nullable();
            $table->unsignedBigInteger('blank_votes')->nullable();
            $table->unsignedBigInteger('null_votes')->nullable();
            $table->unsignedBigInteger('turnout')->nullable();
            $table->unsignedBigInteger('abstentions')->nullable();
            $table->unsignedInteger('total_sections')->nullable();
            $table->unsignedInteger('counted_sections')->nullable();
            $table->decimal('counted_sections_percentage', 7, 4)->nullable();
            $table->string('official_status')->nullable();
            $table->index(['election_round_id', 'scope_id', 'source_reported_at'], 'snapshots_window_index');
        });
        Schema::create('snapshot_candidate_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained()->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('votes');
            $table->decimal('valid_votes_percentage', 7, 4)->nullable();
            $table->string('official_status')->nullable();
            $table->string('vote_destination')->nullable();
            $table->json('official_metadata')->nullable();
            $table->unique(['snapshot_id', 'candidate_id']);
        });
        Schema::create('current_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_round_id')->constrained()->restrictOnDelete();
            $table->foreignId('scope_id')->constrained()->restrictOnDelete();
            $table->foreignId('snapshot_id')->constrained()->restrictOnDelete();
            $table->timestamp('updated_at', 6);
            $table->unique(['election_round_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('current_results');
        Schema::dropIfExists('snapshot_candidate_results');
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('snapshot_imports');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('source_payloads');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('round_candidates');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('scopes');
        Schema::dropIfExists('election_rounds');
        Schema::dropIfExists('elections');
    }
};
