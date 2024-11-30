<?php

namespace App\GraphQL\Mutations;

use App\Services\ProcessMatchday;
use Illuminate\Support\Facades\Log;

class CreateSeason
{
    public function __invoke($_, array $args)
    {
        $seasonId = $args['seasonId'];
        $model_type = $args['model_type'] ?? 'VFE';

        // Perform the logic
        $job = new ProcessMatchday($seasonId, $model_type);
        $returnData = $job->handle();

        // Measure elapsed time
        return [
            'data' => count($returnData) === 240 && !in_array(null, $returnData, true)  ? 'complete' : 'incomplete',
        ];
    }
}
