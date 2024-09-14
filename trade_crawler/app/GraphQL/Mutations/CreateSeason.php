<?php

namespace App\GraphQL\Mutations;

use App\Jobs\ProcessMatchday;

class CreateSeason
{
    public function __invoke($_, array $args)
    {
        $seasonId = $args['seasonId'];
        $job = new ProcessMatchday($seasonId);
        $returnData = $job->handle();
        return [
            'data' => count($returnData) === 240 && !in_array(null, $returnData, true)  ? 'complete' : 'incomplete',
        ];
    }
}
