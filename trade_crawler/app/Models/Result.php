<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use HasFactory;

    protected $fillable = ['num', "season_id", "result", "team_num", "team1", "team2", "team3", "type", "matchday", "same_outcome", "same_outcome2", "same_outcome3", "against_outcome", "against_outcome2", "against_outcome3", "same_odd", "against_odd"];
}
