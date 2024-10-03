<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WinOrDraw extends Model
{
    use HasFactory;
    protected $table = 'win_or_draws';

    protected $fillable = ['season_id', 'matchday_id', 'home', 'away', 'over', 'under', 'result', 'booker_prediction', 'match_id'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
