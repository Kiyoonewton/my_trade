<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class TeamArranger extends Model
{
    use HasFactory;
    protected $table = 'team_arrangers';
    protected $fillable = ['team1', 'team2', 'team3'] ;
}
