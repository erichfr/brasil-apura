<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['response_sha256', 'raw_path', 'source_id', 'source_payload_id', 'started_at', 'finished_at', 'status', 'http_status', 'error_code', 'error_message'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}
