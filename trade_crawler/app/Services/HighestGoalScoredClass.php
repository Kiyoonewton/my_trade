<?php

namespace App\Services;

class HighestGoalScoredClass
{
    protected $topGoals;
    protected $rawDatas;

    public function __construct(mixed $rawDatas)
    {
        $this->rawDatas = $rawDatas;
        $this->topGoals = [
            ["goal" => 0, "teamName" => ""],
            ["goal" => 0, "teamName" => ""]
        ];
    }

    public function calculateHighestGoalScored()
    {
        foreach ($this->rawDatas as $rawData) {
            $total = $rawData["goalsAgainstTotal"] + $rawData["goalsForTotal"];

            if ($total > $this->topGoals[0]["goal"]) {
                $this->topGoals[1] = $this->topGoals[0];
                $this->topGoals[0] = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            } elseif ($total > $this->topGoals[1]["goal"]) {
                $this->topGoals[1] = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            }
        }

        return $this->topGoals;
    }
}
