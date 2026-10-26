<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoundCandidate extends Model
{
    public $timestamps = false;

    protected $fillable = ['election_round_id', 'candidate_id'];
}
