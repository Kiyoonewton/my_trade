<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class TeamWinsAnalysis extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'win_analysis';

    protected $fillable = ['season_id', 'matchday_id', 'home', 'away', 'over', 'under', 'result'];
};
