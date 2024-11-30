<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MatchdayDataClass
{
    protected $matchday;
    protected $matchdayDetails;
    protected $rawDatas;
    protected $pointsTotal;
    protected $highestGoalScored;
    protected $highestGoal;
    protected $doc;
    protected $odds;

    public function __construct(protected array $data)
    {
        $this->doc = $this->data["doc"][0];
        $this->odds = collect($this->doc["data"]["odds"]);
    }

    protected function addMatchId(string $team1, string $team2)
    {
        $matches = DB::table('matches')->get();
        foreach ($matches as $match) {
            if ($match->team1 === $team1 && $match->team2 === $team2 || $match->team1 === $team2 && $match->team2 === $team1) {
                return $match->id;
            }
        }
    }

    public function getOddOrEvenMatchday(): array
    {
        $total = collect($this->odds)->map(function ($odd) {
            return collect($odd["market"])->filter(function ($marketOdd) {
                return $marketOdd["id"] === 26;
            })->map(function ($marketType) use ($odd) {
                $odd_data = $marketType["outcome"][0]["odds"];
                $even_data = $marketType["outcome"][1]["odds"];
                $result_data = $marketType["outcome"][0]["result"];
                return [
                    'odd' => $odd_data,
                    'even' => $even_data,
                    'result' => $result_data,
                    'home' => $odd["teams"]["home"]["name"],
                    'away' => $odd["teams"]["away"]["name"],
                    "matchday_id" => (int) substr($this->data["queryUrl"], strrpos($this->data["queryUrl"], '/') + 1),
                    // 'match_id' => $this->addMatchId($odd["teams"]["home"]["name"], $odd["teams"]["away"]["name"])
                ];
            })->values();
        })->flatten(1);

        return [
            ...$total,
        ];
    }
}
