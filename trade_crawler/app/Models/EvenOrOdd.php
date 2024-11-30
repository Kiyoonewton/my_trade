<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenOrOdd extends Model
{
    use HasFactory;
    protected $table = 'even_or_odd';
    protected $fillable = ['season_id', 'matchday_id', 'home', 'away', 'odd', 'even', 'result', 'match_id'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
