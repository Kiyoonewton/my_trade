<?php

namespace App\Services;

class HighestGoalScoredClass
{
    protected $topGoals;
    protected $rawDatas;

    public function __construct(mixed $rawDatas)
    {
        $this->rawDatas = $rawDatas;
        // Initialize topGoals with 3 entries instead of 2
        $this->topGoals = [
            ["goal" => 0, "teamName" => ""],
            ["goal" => 0, "teamName" => ""],
            ["goal" => 0, "teamName" => ""],
        ];
    }

    public function calculateHighestGoalScored()
    {
        foreach ($this->rawDatas as $rawData) {
            $total = $rawData["goalsAgainstTotal"] + $rawData["goalsForTotal"];

            // Compare the total with the top 3 goals
            if ($total > $this->topGoals[0]["goal"]) {
                // Shift the lower scores down
                $this->topGoals[2] = $this->topGoals[1];
                $this->topGoals[1] = $this->topGoals[0];
                $this->topGoals[0] = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            } elseif ($total > $this->topGoals[1]["goal"]) {
                // Shift the third score down
                $this->topGoals[2] = $this->topGoals[1];
                $this->topGoals[1] = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            } elseif ($total > $this->topGoals[2]["goal"]) {
                // Set the third highest score
                $this->topGoals[2] = ["goal" => $total, "teamName" => $rawData["team"]["name"]];
            }
        }

        return $this->topGoals;
    }
}
