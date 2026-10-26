<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scope extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'type'];
}
