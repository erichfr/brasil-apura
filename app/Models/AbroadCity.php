<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbroadCity extends Model
{
    public $timestamps = false;

    protected $fillable = ['tse_code', 'scope_id', 'name', 'country_code', 'country_name', 'geographic_evidence', 'reference_sha256'];

    protected function casts(): array
    {
        return ['geographic_evidence' => 'array'];
    }
}
