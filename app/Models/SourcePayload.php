<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourcePayload extends Model
{
    public $timestamps = false;

    protected $fillable = ['source_id', 'sha256', 'generation_id', 'storage_path', 'size_bytes', 'etag', 'last_modified', 'source_reported_at', 'collected_at'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'source_reported_at' => 'immutable_datetime', 'collected_at' => 'immutable_datetime'];
    }
}
