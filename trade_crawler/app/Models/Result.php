<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = ['num', "season_id", "team_num", "team1", "team2", "team3", "type", "matchday", "outcome1", "outcome2", "outcome3", "outcome4", 'final_outcome1', 'final_outcome2', "odd", 'result'];
}
