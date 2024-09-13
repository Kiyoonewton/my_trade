<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WinOrDrawMarket extends Model
{
    use HasFactory;

    protected $fillable = ['season_id', 'matchday_id', 'home', 'away', 'over', 'under', 'result'];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
