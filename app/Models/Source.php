<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $fillable = ['election_round_id', 'scope_id', 'file_type', 'url', 'schema_version', 'etag', 'last_modified', 'last_success_at'];

    protected function casts(): array
    {
        return ['last_success_at' => 'immutable_datetime'];
    }
}
