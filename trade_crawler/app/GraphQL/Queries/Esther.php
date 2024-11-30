<?php

namespace App\GraphQL\Queries;

use App\Models\OddOrEven;
use App\Models\OddOrEven1;
use App\Models\OddOrEven2;
use App\Models\Season;
use App\Models\Season1;
use App\Models\Season2;
use Illuminate\Support\Facades\DB;

//this game we will find the highest back to back game that plays same bewtween 1 to 15 and check if the 16-30 will be good 

class Esther
{
  protected function getSeasonId(int $row_number, string $season_type)
  {
    $tableName = (new $season_type)->getTable();

    return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
          FROM {$tableName}, (SELECT @row_num := 0) AS init 
          ORDER BY seasonId) AS subquery"))
      ->where('row_num', $row_number)
      ->pluck('seasonId');
  }

  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  protected function getTeams(string $team, string $seasonId, string $match_model)
  {
    $results = $match_model::where('season_id', $seasonId)
      ->where(function ($query) use ($team) {
        $query->where('home', $team)
          ->orWhere('away', $team);
      })
      ->take(15)->get(['home', 'away', 'matchday_id', 'result']);

    $matches = $results->map(function ($match) {
      return [$match->home, $match->away, $match->matchday_id, $match->result];
    });
    return [
      'team' => $team,
      'matches' => $matches->all(),
    ];
  }

  public function processMatches($matches, $seasons, $set, $match_model)
  {
    // dd($matches, $seasons);
    // return collect($matches)->map(function ($matchSet) use ($seasons) {
    $matchResults = collect($matches)->map(function ($match) use ($seasons, $set, $match_model) {
      return $match_model::where('season_id', $seasons)
        ->whereBetween('matchday_id', $set)
        ->where(function ($query) use ($match) {
          $query->where(function ($subQuery) use ($match) {
            $subQuery->where('home', $match[0])->where('away', $match[1]);
          })->orWhere(function ($subQuery) use ($match) {
            $subQuery->where('home', $match[1])->where('away', $match[0]);
          });
        })
        ->get(['home', 'away', 'matchday_id', 'result', 'even', 'odd', 'season_id']);
    })->all();


    // dd($matchResults);
    $matches = collect($matchResults)->map(function ($item) {
      // return $item;
      return implode(',', [
        $item[0]['season_id'],
        $item[0]['result'],
        $item[0]['home'],
        $item[0]['away'],
        $item[0]['matchday_id'],
        $item[0]['even'],
        $item[0]['odd']
      ]);
      // return [$home, $away, $matchday_id, $result, $odd, $even];
    });

    // dd($matchResults);
    return [
      'matches' =>  $matches->all(),
      'count' => collect($matches)->count()
    ];
    // });
  }

  protected function getArrayOfThelongestStreak($result)
  {
    $maxStreak = 0;
    $currentStreak = 0;
    $lastValue = null;
    $maxSubArrays = [];
    $currentSubArray = [];

    foreach ($result as $value) {
      if ($value[3] === $lastValue) {

        $currentStreak++;
        $currentSubArray[] = $value;
      } else {
        $currentStreak = 1;
        $currentSubArray = [$value];
        $lastValue = $value[3];
      }

      if ($currentStreak > $maxStreak) {
        $maxStreak = $currentStreak;
        $maxSubArrays = [$currentSubArray];
      } elseif ($currentStreak === $maxStreak) {
        $maxSubArrays[] = $currentSubArray;
      }
    }
    return ['streak' => $maxStreak, 'detail' => $maxSubArrays];
  }

  protected function extractSecondColumn(array $data): array
  {
    return array_map(function ($row) {
      // Split the row into parts
      $columns = explode(',', $row);

      // Return the second element (index 1)
      return (int)$columns[1];
    }, $data);
  }

  public function __invoke($_, $args)
  {
    $type = $args['type'] ?? 'vfe';
    $number_of_gt = 0;
    $number_of_gt2 = 0;

    switch ($type) {
      case 'vfl':
        $match_model = OddOrEven1::class;
        $season_model = Season1::class;
        break;
      case 'vfb':
        $match_model = OddOrEven2::class;
        $season_model = Season2::class;
        break;
      default:
        $match_model = OddOrEven::class;
        $season_model = Season::class;
        break;
    }

    for ($i = $args['start']; $i < $args['end']; $i++) {
      $seasonId = $this->getSeasonId($i, $season_model)[0];
      $highestStreak = 0;
      $highestSubStreak = null;

      foreach ($this->teams as $item) {
        $result = $this->getTeams($item, $seasonId, $match_model)['matches'];
        $streakData = $this->getArrayOfThelongestStreak($result);

        if ($streakData['streak'] > $highestStreak) {
          $highestStreak = $streakData['streak'];
          $highestSubStreak = $streakData['detail'][0];
        }
      }
      //first 16-30
      $result = $this->processMatches($highestSubStreak, $seasonId, [16, 30], $match_model);
      if (count($result['matches']) > 7) {
        $number_of_gt++;
        $firstSixElements = array_slice($result['matches'], 0, 8);

        if ((!in_array($highestSubStreak[0][3], $this->extractSecondColumn($firstSixElements)))) print_r([$result['matches'], $highestSubStreak[0][3], 'first', $seasonId]);
      }

      //second 1-15
      $seasonId2 = $this->getSeasonId($i + 1, $season_model)[0];
      $result2 = $this->processMatches($highestSubStreak, $seasonId2, [1, 15], $match_model);
      if (count($result2['matches']) > 7) {
        $number_of_gt2++;
        $firstSixElements2 = array_slice($result2['matches'], 0, 8);

        if ((!in_array($highestSubStreak[0][3], $this->extractSecondColumn($firstSixElements2)))) print_r([$result2['matches'], $highestSubStreak[0][3], 'second', $seasonId2]);
      }
    }
    echo $number_of_gt;
  }
}
