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

    // public function getWinOrDrawMatchday()
    // {
    //     return $this->team1;
    //     $odd = $this->filterMarketByTeam($this->team1, $this->team2);
    //     $odd2 = $this->filterMarketByTeam($this->pointsTotal[1]["teamName"]);
    //     $WinOrDraw1 = collect(collect($odd1["market"])
    //         ->filter(function ($marketOdd) {
    //             return $marketOdd["id"] === 1;
    //         })->values()->first()['outcome'])
    //         ->map(function ($marketOdd) {
    //             if ($marketOdd["id"] === "1") {
    //                 $type = "home";
    //             } elseif ($marketOdd["id"] === "2") {
    //                 $type = "draw";
    //             } else {
    //                 $type = "away";
    //             }
    //             return [
    //                 "type" => $type,
    //                 "odds" => $marketOdd["odds"],
    //                 "result" => $marketOdd["result"]
    //             ];
    //         })->values()->all();
    //     $WinOrDraw2 = collect(collect($odd2["market"])
    //         ->filter(function ($marketOdd) {
    //             return $marketOdd["id"] === 1;
    //         })->values()->first()['outcome'])
    //         ->map(function ($marketOdd) {
    //             if ($marketOdd["id"] === "1") {
    //                 $type = "home";
    //             } elseif ($marketOdd["id"] === "2") {
    //                 $type = "draw";
    //             } else {
    //                 $type = "away";
    //             }
    //             return [
    //                 "type" => $type,
    //                 "odds" => $marketOdd["odds"],
    //                 "result" => $marketOdd["result"]
    //             ];
    //         })->values()->all();

    //     $teams1 = $odd1['teams'];
    //     $teams2 = $odd2['teams'];

    //     $homeOrAway1 = (collect($teams1)->filter(function ($filterPrediction) {
    //         return $filterPrediction['name'] === $this->pointsTotal[0]["teamName"];
    //     }))->keys()->first();
    //     $homeOrAway2 = (collect($teams2)->filter(function ($filterPrediction) {
    //         return $filterPrediction['name'] === $this->pointsTotal[1]["teamName"];
    //     }))->keys()->first();

    //     $outcome1 = (collect($WinOrDraw1)->filter(function ($filterPrediction) use ($homeOrAway1) {
    //         return $filterPrediction['type'] === $homeOrAway1;
    //     }))->values()->first()['result'] === 1 ? '1' : ($WinOrDraw1[1]['result'] === 1 ? 'x' : '2');
    //     $outcome2 = (collect($WinOrDraw2)->filter(function ($filterPrediction) use ($homeOrAway2) {
    //         return $filterPrediction['type'] === $homeOrAway2;
    //     }))->values()->first()['result'] === 1 ? '1' : ($WinOrDraw2[1]['result'] === 1 ? 'x' : '2');

    //     return [
    //         "queryUrl" => $this->data["queryUrl"],
    //         'teams' => ['first_pos' => ["home" => $odd1["teams"]["home"]["name"], "away" => $odd1["teams"]["away"]["name"]], 'second_pos' => ["home" => $odd2["teams"]["home"]["name"], "away" => $odd2["teams"]["away"]["name"]]],
    //         "market" => ['first_pos' => $WinOrDraw1, 'second_pos' => $WinOrDraw2],
    //         "outcome" => ['first_pos' => $outcome1, 'second_pos' => $outcome2],
    //         "prediction" => ['first_pos' => $this->pointsTotal[0]["teamName"], 'second_pos' => $this->pointsTotal[1]["teamName"]]
    //     ];
    // }

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
                return [
                    'over' => $marketType["outcome"][0]["odds"],
                    'under' => $marketType["outcome"][1]["odds"],
                    'result' => $marketType["outcome"][0]["result"],
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
