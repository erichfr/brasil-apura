<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionRound extends Model
{
    protected $fillable = ['election_id', 'number', 'election_date', 'tse_election_code', 'tse_contest_code'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'election_date' => 'immutable_date'];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class);
    }
}
