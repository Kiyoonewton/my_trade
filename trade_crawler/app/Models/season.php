<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Season extends Model
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'seasons';
    protected $primaryKey = 'seasonId';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['seasonId', 'type'];

    public function winOrDrawMarket()
    {
        return $this->hasMany(WinOrDrawMarket::class, 'season_id');
    }

    public function overOrUnderMarket()
    {
        return $this->hasMany(OverOrUnderMarket::class, 'season_id');
    }
}
