<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;
    protected $table = 'matches';
    protected $primaryKey = 'uuid';
    protected $fillable = ['team1', 'team2'];

    public function winOrDrawMarket()
    {
        return $this->hasMany(WinOrDraw::class, 'match_id');
    }

    public function overOrUnderMarket()
    {
        return $this->hasMany(OverOrUnder::class, 'match_id');
    }
}
