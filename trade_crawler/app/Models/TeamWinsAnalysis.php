<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class TeamWinsAnalysis extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'win_analysis';

    protected $fillable = ['season_id', 'matchday_id', 'queryUrl', 'team', 'no_played', 'no_won'];

    public function season()
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
};
