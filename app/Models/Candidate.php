<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = ['election_id', 'tse_candidate_id', 'ballot_name', 'ballot_number', 'party', 'federation', 'official_photo_url'];
}
