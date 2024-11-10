<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class David
{
  // [2884109,2887898,2888593,2889417,2889596,]
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

    // take 2
    $results = OverOrUnder::where('season_id', $seasonId)->where('matchday_id', $index)->where(function ($query) {
      $query->whereBetween('over', [1.60, 1.85])
        ->orWhereBetween('under', [1.60, 1.85]);
    })
      ->orderByRaw('LEAST(ABS(`over` - 1.85), ABS(`under` - 1.85)) ASC')->take(2)->get(['home', 'away', 'over', 'under', 'result', 'matchday_id']);

    //take 3
    // $results = OverOrUnder::where('season_id', $seasonId)->where('matchday_id', $index)->where(function ($query) {
    //   $query->whereBetween('over', [1.60, 1.85])
    //     ->orWhereBetween('under', [1.60, 1.85]);
    // })
    //   ->orderByRaw('LEAST(ABS(`over` - 1.85), ABS(`under` - 1.85)) ASC')->take(3)->get(['home', 'away', 'over', 'under', 'result', 'matchday_id']);

    $matches = $results->map(function ($match) {
      return implode(', ', [
        $match->result,
        $match->home,
        $match->away,
        $match->over,
        $match->under,
        $match->matchday_id
      ]);
    });
    return $matches->all();
  }

  public function __invoke($_, $args)
  {
    $seasonId = $args["season"];
    // $seasonId = $this->getSeasonId(1);
    $result1 = [];
    $result2 = [];
    $result3 = [];
    $results = [];

    for ($i = 3; $i < 11; $i++) {
      $result1[] = $this->getTeams($i, $seasonId);
    }

    $results[] = ['first' => $result1];

    for ($i = 12; $i < 20; $i++) {
      $result2[] = $this->getTeams($i, $seasonId);
    }

    $results[] = ['second' => $result2];


    for ($i = 21; $i < 29; $i++) {
      $result3[] = $this->getTeams($i, $seasonId);
    }

    $results[] = ['third' => $result3];

    return $results;
  }
}
