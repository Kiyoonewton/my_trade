<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = ['num', "season_id", "team1", "team2", "team3", "type", "matchday", "outcome", "odd", 'result'];
}
