<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnapshotCandidateResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['snapshot_id', 'candidate_id', 'votes', 'valid_votes_percentage', 'official_status', 'vote_destination', 'official_metadata'];

    protected function casts(): array
    {
        return ['votes' => 'integer', 'valid_votes_percentage' => 'decimal:4', 'official_metadata' => 'array'];
    }
}
