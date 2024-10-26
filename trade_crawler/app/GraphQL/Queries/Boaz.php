<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;


//this class calculate and draw out rules for every time a particular team is at home
//home across all the 30 matches. And we check the number of time they can go before not play the booker's game
class Boaz
{
  protected array $matchDays = [[1, 30], [16, 30]];

  // protected array $teams = ['Watford'];
  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  protected function getSeasonId(int $row_number)
  {
    return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
    FROM seasons, (SELECT @row_num := 0) AS init 
    ORDER BY seasonId) AS subquery"))
      ->where('row_num', $row_number)
      ->pluck('seasonId');
  }

  protected function getTeams(int $index, int $type, string $seasonId)
  {
    return collect($this->teams)->map(function ($team) use ($index, $type, $seasonId) {
      $results = OverOrUnder::where('season_id', $seasonId)->where(function ($query) use ($team) {
        $query->where(function ($query) use ($team) {
          $query->where('away', $team);
        });
      })->whereBetween('matchday_id', $this->matchDays[$index])->get(['result']);

      $matches = $results->map(function ($match) {
        return $match->result;
      });
      return $matches->all();
    });
  }

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];

    for ($j = $start; $j < $end; $j++) {
      $nextGame1 = $this->getTeams(0, 0, $this->getSeasonId($j + 1)->first());
      $nextGame2 = $this->getTeams(0, 0, $this->getSeasonId($j + 2)->first());
      $nextGame3 = $this->getTeams(0, 0, $this->getSeasonId($j + 3)->first());

      $total = [];
      $highest = 0;

      // count 0 - 5
      for ($k = 0; $k < 16; $k++) {
        $count_same = 0;

        for ($i = 0; $i < 6; $i++) {
          if (($nextGame1[$k][$i] == $nextGame2[$k][$i])  == $nextGame3[$k][$i]) {
            $count_same++;
          }
        }
        $total[] = $count_same;
      }
      // foreach ($nextGame1 as $getGame) {
      //   $maxZeros = 0;
      //   $currentZeros = 0;

      //   foreach ($getGame as $value) {
      //     if ($value == 1) {
      //       $currentZeros++;
      //     } else {

      //       if ($currentZeros > $maxZeros) {
      //         $maxZeros = $currentZeros;
      //       }
      //       $currentZeros = 0;
      //     }
      //   }

      //   if ($currentZeros > $maxZeros) {
      //     $maxZeros = $currentZeros;
      //   }

      //   $total[] = $maxZeros;
      //   $highest = min($total);
      // }
      print_r($nextGame1[8]);
      // print_r($j + 1 . '====>' . $highest);
      echo "\n";
    }
  }
}
//7 6 4 3 7 4 5
