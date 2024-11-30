<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OddOrEven1 extends Model
{
    use HasFactory;
    protected $table = 'odd_or_evens1';
    protected $fillable = ['season_id', 'matchday_id', 'home', 'away', 'odd', 'even', 'result', 'match_id'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season1::class, 'season_id');
    }
}