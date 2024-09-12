<?php

namespace App\Services;

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

    public function __construct(public string $queryUrl, protected array $filteredFeature)
    {
        //...;
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

    public function getOverOrUnderMatchday(): array
    {
        $total = (collect(value: collect(value: $this->filteredFeature["market"])->filter(function ($marketOdd) {
            return $marketOdd["id"] === 18 && $marketOdd["specifiers"] === "total=2.5";
        }))->map(function ($marketType): array {
            $key = explode(separator: "=",  string: $marketType["specifiers"]);
            $key = array_map(callback: 'trim',  array: $key);

            return  [['type' => 'over', "odds" => $marketType["outcome"][0]["odds"], "result" => $marketType["outcome"][0]["result"]], ['type' => 'under', "odds" => $marketType["outcome"][1]["odds"], "result" => $marketType["outcome"][1]["result"]]];
        }))->first();

        return [
            "matchday_id" => (int) substr($this->queryUrl, strrpos($this->queryUrl, '/') + 1),
            'result' => $total[0]['result'],
            'over' => $total[0]['odds'],
            'under' => $total[1]['odds'],
            "home" => $this->filteredFeature["teams"]["home"]["name"],
            "away" => $this->filteredFeature["teams"]["away"]["name"]
        ];
    }
}
