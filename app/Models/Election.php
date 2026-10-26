<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    protected $fillable = ['year', 'name', 'office_code'];

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ElectionRound::class);
    }
}
