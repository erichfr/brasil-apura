<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_round_id', 'scope_id', 'snapshot_id', 'updated_at'];

    protected function casts(): array
    {
        return ['updated_at' => 'immutable_datetime'];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }
}
