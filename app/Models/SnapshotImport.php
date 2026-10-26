<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnapshotImport extends Model
{
    public $timestamps = false;

    protected $fillable = ['origin', 'verification_notes', 'manifest_sha256', 'imported_at'];

    protected function casts(): array
    {
        return ['imported_at' => 'immutable_datetime'];
    }
}
