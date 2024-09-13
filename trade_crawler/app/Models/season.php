<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;
    protected $primaryKey = 'seasonId';
    protected $fillable = ['seasonId'];

    public function winOrDrawMarket()
    {
        return $this->hasMany(WinOrDrawMarket::class, 'season_id');
    }

    public function overOrUnderMarket()
    {
        return $this->hasMany(OverOrUnderMarket::class, 'season_id');
    }
}
