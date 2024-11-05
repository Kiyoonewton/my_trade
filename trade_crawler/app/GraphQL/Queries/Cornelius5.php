<?php

namespace App\GraphQL\Queries;

use App\Models\TeamArranger;
use Illuminate\Support\Facades\DB;

class Cornelius5
{
    protected $teamNumber = [];
    protected function getThreeTeams($id)
    {
        return TeamArranger::where('id', $id)->get(['team1', 'team2', 'team3'])->map(function ($team) {
            return [$team->team1, $team->team2, $team->team3];
        })->all();
    }

    protected function getSeasonId(int $row_number)
    {
        return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
    FROM seasons, (SELECT @row_num := 0) AS init 
    ORDER BY seasonId) AS subquery"))
            ->where('row_num', $row_number)
            ->pluck('seasonId');
    }
    public function __invoke($query, $args) {}
}
