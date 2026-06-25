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

    public function getWinOrDrawMatchday(): array
    {
        $total = collect($this->odds)->map(function ($odd) {
            return collect($odd["market"])->filter(function ($marketOdd) {
                return $marketOdd["id"] === 1;
            })->map(function ($marketType) use ($odd) {
                $outcomes = collect($marketType["outcome"])->map(function ($outcome) {
                    if ($outcome["id"] === "1") {
                        $type = "home";
                    } elseif ($outcome["id"] === "2") {
                        $type = "draw";
                    } else {
                        $type = "away";
                    }
                    return [
                        "type" => $type,
                        "odds" => $outcome["odds"],
                        "result" => $outcome["result"]
                    ];
                })->values()->all();

                $homeResult = collect($outcomes)->firstWhere('type', 'home')['result'] ?? 0;
                $drawResult = collect($outcomes)->firstWhere('type', 'draw')['result'] ?? 0;
                $result = $homeResult === 1 ? '1' : ($drawResult === 1 ? 'x' : '2');

                return [
                    'home' => $odd["teams"]["home"]["name"],
                    'away' => $odd["teams"]["away"]["name"],
                    'home_odd' => collect($outcomes)->firstWhere('type', 'home')['odds'] ?? null,
                    'draw_odd' => collect($outcomes)->firstWhere('type', 'draw')['odds'] ?? null,
                    'away_odd' => collect($outcomes)->firstWhere('type', 'away')['odds'] ?? null,
                    'result' => $result,
                    'matchday_id' => (int) substr($this->data["queryUrl"], strrpos($this->data["queryUrl"], '/') + 1),
                    'match_id' => $this->addMatchId($odd["teams"]["home"]["name"], $odd["teams"]["away"]["name"])
                ];
            })->values();
        })->flatten(1);

        return [...$total];
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

    public function getOverOrUnderMatchday(): array
    {
        $total = collect($this->odds)->map(function ($odd) {
            return collect($odd["market"])->filter(function ($marketOdd) {
                return $marketOdd["id"] === 18 && $marketOdd["specifiers"] === "total=2.5";
            })->map(function ($marketType) use ($odd) {
                $over_data = $marketType["outcome"][0]["odds"];
                $under_data = $marketType["outcome"][1]["odds"];
                $result_data = $marketType["outcome"][0]["result"];
                return [
                    'over' => $over_data,
                    'under' => $under_data,
                    'result' => $result_data,
                    'booker_prediction' => $over_data < $under_data ?
                        $marketType["outcome"][0]["result"] : $marketType["outcome"][1]["result"],
                    'home' => $odd["teams"]["home"]["name"],
                    'away' => $odd["teams"]["away"]["name"],
                    "matchday_id" => (int) substr($this->data["queryUrl"], strrpos($this->data["queryUrl"], '/') + 1),
                    'match_id' => $this->addMatchId($odd["teams"]["home"]["name"], $odd["teams"]["away"]["name"])
                ];
            })->values();
        })->flatten(1);
        return [
            ...$total,
        ];
    }
}
