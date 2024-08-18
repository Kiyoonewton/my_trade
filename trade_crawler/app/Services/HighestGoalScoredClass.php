<?php

namespace App\Services;

class HighestGoalScoredClass
{
    protected $highestGoal;
    protected $rawDatas;

    public function __construct(mixed $rawDatas)
    {
        $this->rawDatas = $rawDatas;
        $this->highestGoal = ["goal" => 0, "teamName" => ""];
    }

    public function calculateHighestGoalScored()
    {
        foreach ($this->rawDatas as $rawData) {

            $total = $rawData["goalsAgainstTotal"] + $rawData["goalsForTotal"];
            if ($total > $this->highestGoal["goal"]) {
                $this->highestGoal = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            }
        }
        return $this->highestGoal;
    }
}
