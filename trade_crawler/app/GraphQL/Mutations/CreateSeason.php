<?php

namespace App\GraphQL\Mutations;

use App\Jobs\ProcessMatchday;

class CreateSeason
{
    public function __invoke($_, array $args)
    {
        $seasonId = $args['seasonId'];
        $team1 = $args['team1'];
        $team2 = $args['team2'];
        $job = new ProcessMatchday($seasonId, $team1, $team2);
        $returnData = $job->handle();
        if ($returnData) {
            return [
                '__typename' => 'MatchList',
                'data' => $returnData
            ];
        }
        return [
            '__typename' => 'StringResponse',
            'message' => 'Already Existing',
        ];
    }
}
