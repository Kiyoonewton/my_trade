<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class David3
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
      $query->whereBetween('over', [1.85, 1.85])
        ->orWhereBetween('under', [1.85, 1.85]);
    })
      ->orderByRaw('LEAST(ABS(`over` - 1.85), ABS(`under` - 1.85)) ASC')->take(1)->get(['home', 'away', 'over', 'under', 'result', 'matchday_id']);

    $matches = $results->map(function ($match) {
      return
        $match->result;
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
      $result4 = [];
      $result5 = [];
      $results = [];

      for ($i = 1; $i < 31; $i++) {
        print_r($this->getTeams($i, $seasonId));

        // $results[] = in_array(1, $result1, true) ? 1 : 'loss';

        // for ($i = 7; $i < 12; $i++) {
        //   $result2[] = count(array_unique($this->getTeams($i, $seasonId))) === 1 && $this->getTeams($i, $seasonId)[0] === 1 ? 1 : 0;
        // }

        // $results[] = in_array(1, $result2, true) ? 1 : 'loss';

        // for ($i = 13; $i < 18; $i++) {
        //   $result3[] = count(array_unique($this->getTeams($i, $seasonId))) === 1 && $this->getTeams($i, $seasonId)[0] === 1 ? 1 : 0;
        // }

        // $results[] = in_array(1, $result3, true) ? 1 : 'loss';

        // for ($i = 19; $i < 24; $i++) {
        //   $result4[] = count(array_unique($this->getTeams($i, $seasonId))) === 1 && $this->getTeams($i, $seasonId)[0] === 1 ? 1 : 0;
        // }

        // $results[] = in_array(1, $result4, true) ? 1 : 'loss';

        // for ($i = 25; $i < 30; $i++) {
        //   $result5[] = count(array_unique($this->getTeams($i, $seasonId))) === 1 && $this->getTeams($i, $seasonId)[0] === 1 ? 1 : 0;
        // }

        // $results[] = in_array(1, $result5, true) ? 1 : 'loss';
        //https://vgls-vs001.akamaized.net/vfl/feeds/?/bet9javirtuals/en/Africa:Lagos/gismo/stats_season_fixtures2/2893129
        // echo implode(', ', [$seasonId, implode(', ', $results)]) . "\n";
      }
    }
  }
}
