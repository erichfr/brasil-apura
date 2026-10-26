<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Snapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_round_id', 'scope_id', 'source_payload_id', 'snapshot_import_id', 'source_generated_at', 'votes_disclosed', 'official_metadata', 'source_reported_at', 'collected_at', 'total_votes', 'valid_votes', 'blank_votes', 'null_votes', 'turnout', 'abstentions', 'total_sections', 'counted_sections', 'counted_sections_percentage', 'official_status'];

    protected function casts(): array
    {
        return ['source_generated_at' => 'immutable_datetime', 'votes_disclosed' => 'boolean', 'official_metadata' => 'array', 'source_reported_at' => 'immutable_datetime', 'collected_at' => 'immutable_datetime', 'total_votes' => 'integer', 'valid_votes' => 'integer', 'blank_votes' => 'integer', 'null_votes' => 'integer', 'turnout' => 'integer', 'abstentions' => 'integer', 'counted_sections_percentage' => 'decimal:4'];
    }

    public function results(): HasMany
    {
        return $this->hasMany(SnapshotCandidateResult::class);
    }

    public function payload(): BelongsTo
    {
        return $this->belongsTo(SourcePayload::class, 'source_payload_id');
    }
}
