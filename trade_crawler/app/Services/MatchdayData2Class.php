<?php

namespace App\Services;

class MatchdayData2Class
{
    protected $matchday;
    protected $matchdayDetails;
    protected $rawDatas;
    protected $pointsTotal;
    protected $highestGoalScored;
    protected $highestGoal;
    protected $doc;
    protected $odds;

    public function __construct()
    {
        $path = storage_path('App/Data/data.json');
        $json = file_get_contents($path);
        $this->matchday = json_decode($json, true);

        $this->doc = $this->matchday["doc"][0];
        $this->odds = collect($this->doc["data"]["odds"]);

        $path2 = storage_path('App/Data/data2.json');
        $json2 = file_get_contents($path2);
        $this->matchdayDetails = json_decode($json2, true);

        $this->rawDatas = $this->matchdayDetails["doc"][0]["data"]["tables"][0]["tablerows"];

        $this->pointsTotal = [["pointsTotal" => $this->rawDatas[0]["pointsTotal"], "teamName" => $this->rawDatas[0]["team"]["name"]], ["pointsTotal" => $this->rawDatas[1]["pointsTotal"], "teamName" => $this->rawDatas[1]["team"]["name"]]];

        $highestGoalScored = new HighestGoalScoredClass($this->rawDatas);
        $this->highestGoal = $highestGoalScored->calculateHighestGoalScored();
    }
    protected function filterMarketByTeam(string $team)
    {
        return collect($this->odds->filter(
            function ($odd) use ($team) {
                return $odd["teams"]["home"]["name"] === $team || $odd["teams"]["away"]["name"] === $team;
            }
        ))->first();
    }
    public function getWinOrDrawMatchday()
    {
        $odd1 = $this->filterMarketByTeam($this->pointsTotal[0]["teamName"]);
        $odd2 = $this->filterMarketByTeam($this->pointsTotal[1]["teamName"]);
        $WinOrDraw1 = collect(collect($odd1["market"])
            ->filter(function ($marketOdd) {
                return $marketOdd["id"] === 1;
            })->values()->first()['outcome'])
            ->map(function ($marketOdd) {
                if ($marketOdd["id"] === "1") {
                    $type = "home";
                } elseif ($marketOdd["id"] === "2") {
                    $type = "draw";
                } else {
                    $type = "away";
                }
                return [
                    "type" => $type,
                    "odds" => $marketOdd["odds"],
                    "result" => $marketOdd["result"]
                ];
            })->values()->all();
        $WinOrDraw2 = collect(collect($odd2["market"])
            ->filter(function ($marketOdd) {
                return $marketOdd["id"] === 1;
            })->values()->first()['outcome'])
            ->map(function ($marketOdd) {
                if ($marketOdd["id"] === "1") {
                    $type = "home";
                } elseif ($marketOdd["id"] === "2") {
                    $type = "draw";
                } else {
                    $type = "away";
                }
                return [
                    "type" => $type,
                    "odds" => $marketOdd["odds"],
                    "result" => $marketOdd["result"]
                ];
            })->values()->all();

        // return [$WinOrDraw1, $WinOrDraw2];
        $teams1 = $odd1['teams'];
        $teams2 = $odd2['teams'];

        $homeOrAway1 = (collect($teams1)->filter(function ($filterPrediction) {
            return $filterPrediction['name'] === $this->pointsTotal[0]["teamName"];
        }))->keys()->first();
        $homeOrAway2 = (collect($teams2)->filter(function ($filterPrediction) {
            return $filterPrediction['name'] === $this->pointsTotal[1]["teamName"];
        }))->keys()->first();

        $outcome1 = (collect($WinOrDraw1)->filter(function ($filterPrediction) use ($homeOrAway1) {
            return $filterPrediction['type'] === $homeOrAway1;
        }))->values()->first()['result'] === 1 ? '1' : ($WinOrDraw1[1]['result'] === 1 ? 'x' : '2');
        $outcome2 = (collect($WinOrDraw2)->filter(function ($filterPrediction) use ($homeOrAway2) {
            return $filterPrediction['type'] === $homeOrAway2;
        }))->values()->first()['result'] === 1 ? '1' : ($WinOrDraw2[1]['result'] === 1 ? 'x' : '2');

        return [
            "queryUrl" => $this->matchday["queryUrl"],
            'teams' => ['first_pos' => ["home" => $odd1["teams"]["home"]["name"], "away" => $odd1["teams"]["away"]["name"]], 'second_pos' => ["home" => $odd2["teams"]["home"]["name"], "away" => $odd2["teams"]["away"]["name"]]],
            "market" => ['first_pos' => $WinOrDraw1, 'second_pos' => $WinOrDraw2],
            "outcome" => ['first_pos' => $outcome1, 'second_pos' => $outcome2],
            "prediction" => ['first_pos' => $this->pointsTotal[0]["teamName"], 'second_pos' => $this->pointsTotal[1]["teamName"]]
        ];
    }

    public function getOverOrUnderMatchday()
    {
        $highestGoalTeam1 = $this->highestGoal[0]['teamName'];
        $highestGoalTeam2 = $this->highestGoal[1]['teamName'];
        $odd1 = $this->filterMarketByTeam($highestGoalTeam1);
        $odd2 = $this->filterMarketByTeam($highestGoalTeam2);
        $total1 = (collect(collect($odd1["market"])->filter(function ($marketOdd) {
            return $marketOdd["id"] === 18 && $marketOdd["specifiers"] === "total=2.5";
        }))->map(function ($marketType) {
            $key = explode("=",  $marketType["specifiers"]);
            $key = array_map('trim',  $key);

            return ["first_pos" => [['type' => 'over', "odds" => $marketType["outcome"][0]["odds"], "result" => $marketType["outcome"][0]["result"]], ['type' => 'under', "odds" => $marketType["outcome"][1]["odds"], "result" => $marketType["outcome"][1]["result"]]]];
        }))->first();

        $total2 = (collect(collect($odd2["market"])->filter(function ($marketOdd) {
            return $marketOdd["id"] === 18 && $marketOdd["specifiers"] === "total=2.5";
        }))->map(function ($marketType) {
            $key = explode("=",  $marketType["specifiers"]);
            $key = array_map('trim',  $key);

            return ["second_pos" => [['type' => 'over', "odds" => $marketType["outcome"][0]["odds"], "result" => $marketType["outcome"][0]["result"]], ['type' => 'under', "odds" => $marketType["outcome"][1]["odds"], "result" => $marketType["outcome"][1]["result"]]]];
        }))->first();
        return ["queryUrl" => $this->matchday["queryUrl"], 'prediction' => ["first_pos" => 'Over2.5', "second_pos" => 'Over2.5'], "teams" => [
            "first_pos" => ["home" => $odd1["teams"]["home"]["name"], "away" => $odd1["teams"]["away"]["name"]],
            "second_pos" => ["home" => $odd2["teams"]["home"]["name"], "away" => $odd2["teams"]["away"]["name"]]
        ], 'market' => [...$total1, ...$total2], 'outcome' => ["first_pos" => $total1['first_pos'][0]['result'] === 1 ? '1' : '2', "second_pos" => $total2['second_pos'][0]['result'] === 1 ? '1' : '2']];
    }
}
