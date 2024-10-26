<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class Cornelius
{
  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea'];

  protected function arrangeTeam()
  {
    $result = [];
    for ($i = 0; $i < 3; $i++) {
      for ($j = $i + 1; $j < 3; $j++) {
        $result[] =  [
          $this->teams[$i],
          $this->teams[$j]
        ];
      }
    }
    return ['matches' => $result];
    // collect($this->teams)->combinations(2)->map(function ($team) {
    //   return [$team[0], $team[1]];
    // });
  }

  protected function getSeasonId(int $row_number)
  {
    return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
    FROM seasons, (SELECT @row_num := 0) AS init 
    ORDER BY seasonId) AS subquery"))
      ->where('row_num', $row_number)
      ->pluck('seasonId');
  }

  public function processMatches($matches, $seasons, $matchDays_array)
  {
    return collect($matches)->map(function ($matchSet) use ($seasons, $matchDays_array) {
      $matchResults = collect($matchSet['matches'])->map(function ($match) use ($seasons, $matchDays_array) {
        return OverOrUnder::where('season_id', $seasons)
          ->whereBetween('matchday_id', $matchDays_array)->where(function ($query) use ($match) {
            $query->where('home', $match[0])->orWhere('home', $match[1]);
          })->where(function ($query) use ($match) {
            $query->where('away', $match[0])->orWhere('away', $match[1]);
          })
          ->get(['home', 'away', 'matchday_id', 'over', 'under', 'result'])
          ->map(function ($item) {
            $home = $item->home;
            $away = $item->away;
            $over = $item->over;
            $under = $item->under;
            $result = $item->result;
            $matchday_id = $item->matchday_id;
            return $result;
          });
        // dd($match[0]);
      })->flatten(1)->all();
      return [
        // 'team' => $matchSet['team'],
        'matches' => $matchResults,
        'count' => collect($matchResults)->count()
      ];
    });
  }

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];
    $total1 = [];
    $total2 = [];
    $num = 0;

    for ($j = $start; $j < $end; $j++) {
      $total1[] = $this->processMatches([
        $this->arrangeTeam()
      ], $this->getSeasonId($j + 1)->first(), [1, 30])->first();
      $total2[] = $this->processMatches([
        $this->arrangeTeam()
      ], $this->getSeasonId($j + 2)->first(), [1, 30])->first();
      $num = $j;
    }
    for ($k = 0; $k < collect($total1)->count(); $k++) {
      // if (collect($total1)->all()[$k]['matches'] === collect($total2)->all()[$k]['matches']) {
      print_r(array_diff_assoc(collect($total1)->all()[$k]['matches'], collect($total2)->all()[$k]['matches']));
      // }
      // print_r($j + 1);
    }
  }
}
