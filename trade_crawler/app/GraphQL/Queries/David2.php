<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class David2
{

  protected function getSeasonId(int $row_number)
  {
    return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
    FROM seasons, (SELECT @row_num := 0) AS init 
    ORDER BY seasonId) AS subquery"))
      ->where('row_num', $row_number)
      ->pluck('seasonId')[0];
  }

  protected function getTeams(int $index, string $seasonId)
  {
    $results = OverOrUnder::where('season_id', $seasonId)->where('matchday_id', $index)->where(function ($query) {
      $query->whereBetween('over', [1.60, 1.85])
        ->orWhereBetween('under', [1.60, 1.85]);
    })
      ->orderByRaw('LEAST(ABS(`over` - 1.85), ABS(`under` - 1.85)) ASC')->take(3)->get(['home', 'away', 'over', 'under', 'booker_prediction', 'matchday_id']);

    $matches = $results->map(function ($match) {
      return
        $match->booker_prediction;
    });
    return $matches->all();
  }

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];
    for ($j = $start; $j < $end; $j++) {
      $seasonId = $this->getSeasonId($j);
      $result1 = [];
      $result2 = [];
      $result3 = [];
      $results = [];

      for ($i = 3; $i < 11; $i++) {
        $result1[] = count(array_filter($this->getTeams($i, $seasonId), fn($array) => $array === 0)) === 1;
      }
      print_r($this->getTeams($i, $seasonId));
      $results[] = in_array(true, $result1, true);
      // print $result1;

      for ($i = 12; $i < 20; $i++) {
        $result2[] = count(array_filter($this->getTeams($i, $seasonId), fn($array) => $array === 0)) === 1;
      }

      $results[] = in_array(true, $result2, true);

      for ($i = 21; $i < 29; $i++) {
        $result3[] = count(array_filter($this->getTeams($i, $seasonId), fn($array) => $array === 0)) === 1;
      }

      $results[] = in_array(true, $result3, true);

      echo implode(', ', [$seasonId, implode(', ', $results)]) . "\n";
    }
  }
}
