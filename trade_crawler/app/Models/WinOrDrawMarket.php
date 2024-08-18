<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class WinOrDrawMarket extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'win_or_draw';

    protected $fillable = ['season_id', 'matchday_id', 'queryUrl', 'home', 'away', 'market', 'outcome', 'prediction'];

    public function season()
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
