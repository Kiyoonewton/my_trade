<?php

namespace App\GraphQL\Mutations;

use App\Jobs\ProcessMatchday;
use App\Models\Season;

class CreateSeason
{
    public function __invoke($_, array $args)
    {
        $seasonId = $args['seasonId'];
        $team1 = $args['team1'];
        $team2 = $args['team2'];
        dispatch(new ProcessMatchday($seasonId, $team1, $team2));
        return ['season_id' => $seasonId, 'team1' => $team1, 'team2' => $team2];
    }
}
