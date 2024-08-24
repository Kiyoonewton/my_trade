<?php

namespace App\Services;

class WinAnalysisClass
{
    protected $table_url = "";
    public function __construct(public string $seasonId)
    {
        $this->table_url = env('TABLE_URL');
    }
    public function initializeAnalysis()
    {
        $apiUrls = [
            $this->table_url . '/' . $this->seasonId . '/1/15',
            $this->table_url . '/' . $this->seasonId . '/1/30'
        ];
        $response = file_get_contents($apiUrls[0]);
        $data = json_decode($response, true);
        $rawData1 = $data["doc"][0]["data"]["tables"][0]["tablerows"][0];
        $rawData2 = $data["doc"][0]["data"]["tables"][0]["tablerows"][1];

        $team1 = $rawData1['team']['name'];
        $team2 = $rawData2['team']['name'];

        $response1 = file_get_contents($apiUrls[1]);
        $data = json_decode($response1, true);
        $rawData3 = $data["doc"][0]["data"]["tables"][0]["tablerows"];
        $filterFirstAndSecondTeam1 = collect($rawData3)->filter(function ($rawData) use ($team1) {
            return $rawData['team']['name'] === $team1;
        })->values()->first();
        $filterFirstAndSecondTeam2 = collect($rawData3)->filter(function ($rawData) use ($team2) {
            return $rawData['team']['name'] === $team2;
        })->values()->first();

        $home_no1 = 15 - $rawData1['home'];
        $away_no1 = $rawData1['home'];
        $home_no2 = 15 - $rawData2['home'];
        $away_no2 = $rawData2['home'];
        $home_win1 = $filterFirstAndSecondTeam1['winHome'] - $rawData1['winHome'];
        $away_win1 = $filterFirstAndSecondTeam1['winAway'] - $rawData1['winAway'];
        $home_win2 = $filterFirstAndSecondTeam2['winHome'] - $rawData2['winHome'];
        $away_win2 = $filterFirstAndSecondTeam2['winAway'] - $rawData2['winAway'];

        return ['season_id' => $this->seasonId, 'matchday_id' => 30, 'queryUrl' => $data['queryUrl'], 'team' => ['first_pos' => $team1, 'second_pos' => $team2], 'no_played' => ['first_pos' => ['home' => $home_no1, 'away' => $away_no1], 'second_pos' => ['home' => $home_no2, 'away' => $away_no2]], 'no_won' => ['first_pos' => ['home' => $home_win1, 'away' => $away_win1], 'second_pos' => ['home' => $home_win2, 'away' => $away_win2],]];
    }
}
