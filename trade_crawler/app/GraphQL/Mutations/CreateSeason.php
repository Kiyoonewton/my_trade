<?php

namespace App\GraphQL\Mutations;

use App\Jobs\ProcessMatchday;
use App\Models\Season;

class CreateSeason
{
    public function __invoke($_, array $args)
    {
        $seasonId = $args['seasonId'];
        $season = Season::where('seasonId', $seasonId)->first();
        if (!$season) {
            Season::create(['seasonId' => $seasonId, 'type' => ($args['vflId'] === 3) ? 'VFLM' : (($args['vflId'] === 7) ? 'VFEL' : 'VFB')]);
        }
        dispatch(new ProcessMatchday($seasonId));
        return ['season_id' => $seasonId];
    }
}
