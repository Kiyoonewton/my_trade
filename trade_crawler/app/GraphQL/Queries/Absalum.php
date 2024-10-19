<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class Absalum
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
        $query->where('home', $team);
      })->whereBetween('matchday_id', $this->matchDays[$index])->where('booker_prediction', $type)->get(['home', 'away', 'over', 'under', 'matchday_id']);

      $matches = $results->map(function ($match) {
        return [$match->home, $match->away, $match->over, $match->under, $match->matchday_id];
      });
      return [
        'team' => $team,
        'matches' => $matches->all(),
        'count' => $matches->count()
      ];
    });
  }

  public function processMatches($matches, $type, $seasons, $matchDays_array)
  {
    return collect($matches)->map(function ($matchSet) use ($type, $seasons, $matchDays_array) {
      $matchResults = collect($matchSet['matches'])->map(function ($match) use ($seasons, $matchDays_array, $type) {
        return OverOrUnder::where('season_id', $seasons)
          ->whereBetween('matchday_id', $matchDays_array)
          ->where('home', $match[0])
          ->where('away', $match[1])
          ->where('booker_prediction', $type)
          // ->pluck('home')->first();
          ->get(['home', 'away', 'matchday_id', 'over', 'under'])
          ->map(function ($item) {

            $home = $item->home;
            $away = $item->away;
            $over = $item->over;
            $under = $item->under;
            $matchday_id = $item->matchday_id;
            return [$home, $away, $matchday_id, $over, $under];        // Convert each model instance to an array
          });
      })->flatten(1)->all();
      // });

      // $success = $matchResults->contains($type) ? 'success' : 'fail';

      return [
        'team' => $matchSet['team'],
        'matches' => $matchResults,
        'count' => collect($matchResults)->count()
      ];
    });
  }

  // public function __invoke($_, $args)
  // {
  //   $start = $args['start'];
  //   $end = $args['end'];
  //   // $team_index = $args['team_index'];
  //   $total = [];
  //   for ($j = $start; $j < $end; $j++) {
  //     $teams = $this->getTeams(0, 0, $this->getSeasonId($j + 1)->first());
  //     $nextGame1 = $this->processMatches($teams, 0, $this->getSeasonId($j + 2)->first(), $this->matchDays[0],);
  //     $nextGame2 = $this->processMatches($nextGame1, 0, $this->getSeasonId($j + 3)->first(), $this->matchDays[0],);
  //     // $nextGame3 = $this->processMatches($nextGame2, 0, $this->getSeasonId($j + 4)->first(), $this->matchDays[0],);
  //     // $nextGame4 = $this->processMatches($nextGame3, 0, $this->getSeasonId($j + 5)->first(), $this->matchDays[0],);
  //     // $nextGame5 = $this->processMatches($nextGame4, 0, $this->getSeasonId($j + 6)->first(), $this->matchDays[0],);
  //     // $nextGame6 = $this->processMatches($nextGame5, 0, $this->getSeasonId($j + 7)->first(), $this->matchDays[0],);
  //     // $nextGame7 = $this->processMatches($nextGame6, 0, $this->getSeasonId($j + 8)->first(), $this->matchDays[0],);
  //     // $nextGame8 = $this->processMatches($nextGame7, 0, $this->getSeasonId($j + 9)->first(), $this->matchDays[0],);
  //     // $nextGame9 = $this->processMatches($nextGame8, 0, $this->getSeasonId($j + 10)->first(), $this->matchDays[0],);

  //     // return ['first' => $teams->all(), 'second' => $nextGame1->all(), 'third' => $nextGame2->all(), 'fourth' => $nextGame3->all()];
  //     // for ($i = 0; $i < 1; $i++) {

  //     // }
  //     $result = [];

  //     for ($i = 0; $i < 16; $i++) {
  //       if ((in_array($teams[$i]['count'], [6, 5]) && $teams[$i]['count'] === $nextGame1[$i]['count'])) {
  //         $result[] =
  //           // [
  //           //   'first' => ['match' => $teams[$i]['matches'], 'season' => $this->getSeasonId($j + 1)->first()],
  //           //   'second' => [$nextGame1[$i]['matches'], 'season' => $this->getSeasonId($j + 2)->first()],
  //           //   'third' => [$nextGame2[$i]['matches'], 'season' => $this->getSeasonId($j + 3)->first()],
  //           //   'fourth' => [$nextGame3[$i]['matches'], 'season' => $this->getSeasonId($j + 4)->first()],
  //           //   'fifth' => [$nextGame4[$i]['matches'], 'season' => $this->getSeasonId($j + 5)->first()],
  //           //   'sixth' => [$nextGame5[$i]['matches'], 'season' => $this->getSeasonId($j + 6)->first()],

  //           // ];

  //           [
  //             'first' => $teams[$i]['count'],
  //             'second' => $nextGame1[$i]['count'],
  //             'third' => $nextGame2[$i]['count'],
  //             // 'fourth' => $nextGame3[$i]['count'],
  //             // 'fifth' => $nextGame4[$i]['count'],
  //             // 'sixth' => $nextGame5[$i]['count'],

  //           ];
  //       }
  //       // 'fifth' => $nextGame4[$i]['count'],
  //       // 'sixth' => $nextGame5[$i]['count'],
  //       // 'seven' => $nextGame6[$i]['count'],
  //       // 'eight' => $nextGame7[$i]['count'],
  //       // 'nine' => $nextGame8[$i]['count'],
  //       // 'ten' => $nextGame9[$i]['count'],
  //       // ];
  //     }

  //     $total[] = ['result' => $result, 'number' => $j + 1, 'season' => $this->getSeasonId($j + 1)->first()];
  //     // $total[] = ['result' => in_array(true, $result), 'number' => $j + 1, 'season' => $this->getSeasonId($j + 1)->first()];
  //   }
  //   return $total;
  // }

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];
    $total = [];

    for ($j = $start; $j < $end; $j++) {
      $nextGame1 = $this->getTeams(0, 0, $this->getSeasonId($j + 1)->first());
      $nextGame2 = $this->processMatches($nextGame1, 0, $this->getSeasonId($j + 2)->first(), $this->matchDays[0]);
      $nextGame3 = $this->processMatches($nextGame2, 0, $this->getSeasonId($j + 3)->first(), $this->matchDays[0]);
      $nextGame4 = $this->processMatches($nextGame3, 0, $this->getSeasonId($j + 4)->first(), $this->matchDays[0],);
      $nextGame5 = $this->processMatches($nextGame4, 0, $this->getSeasonId($j + 5)->first(), $this->matchDays[0],);
      $nextGame6 = $this->processMatches($nextGame5, 0, $this->getSeasonId($j + 6)->first(), $this->matchDays[0],);
      $nextGame7 = $this->processMatches($nextGame6, 0, $this->getSeasonId($j + 7)->first(), $this->matchDays[0],);
      $nextGame8 = $this->processMatches($nextGame7, 0, $this->getSeasonId($j + 8)->first(), $this->matchDays[0],);
      $nextGame9 = $this->processMatches($nextGame8, 0, $this->getSeasonId($j + 9)->first(), $this->matchDays[0],);
      // $nextGame10 = $this->processMatches($nextGame9, 0, $this->getSeasonId($j + 10)->first(), $this->matchDays[0],);
      // $nextGame11 = $this->processMatches($nextGame10, 0, $this->getSeasonId($j + 11)->first(), $this->matchDays[0],);
      // $nextGame12 = $this->processMatches($nextGame11, 0, $this->getSeasonId($j + 12)->first(), $this->matchDays[0],);
      // $nextGame13 = $this->processMatches($nextGame12, 0, $this->getSeasonId($j + 13)->first(), $this->matchDays[0],);

      $result = [];

      for ($i = 0; $i < 16; $i++) {
        if (in_array($nextGame5[$i]['count'], [1, 2, 3, 4, 5, 6])) {
          $result[] = [
            'first' => $nextGame1[$i]['count'],
            'second' => $nextGame2[$i]['count'],
            'third' => $nextGame3[$i]['count'],
            'fourth' => $nextGame4[$i]['count'],
            'fifth' => $nextGame5[$i]['count'],
            'sixth' => $nextGame6[$i]['count'],
            'seventh' => $nextGame7[$i]['count'],
            'eight' => $nextGame8[$i]['count'],
            // 'nine' => $nextGame9[$i]['count'],
            // 'ten' => $nextGame10[$i]['count'],
            // 'eleven' => $nextGame11[$i]['count'],
            // 'twelve' => $nextGame12[$i]['count'],
            // 'thirteen' => $nextGame13[$i]['count'],
          ];
          print_r($result,);
          // } else {
        }
      }
      print_r($j + 1);

      // Add current result to total array
      // $total[] = ['result' => $result, 'number' => $j + 1, 'season' => $this->getSeasonId($j + 1)->first()];

      // Output the result after each loop iteration
      print_r($total); // or var_dump($total) for more detailed output
    }

    // return $total;
  }
}
        //   1 2 3
// number:19 5 5 3
// number:48 5 5 2
// number:57 5 5 3
// number:74 5 5 4
// number:75 5 5 0
// number:83 5 5 2
// number:99 6 6 2
// number:111 5 5 0
// number:114 6 6 2
// number:130 6 6 2
// number:137 6 6 2
// number:143 6 6 2
// number:172 5 5 3
// number:189 5 5 4  
// number:189 5 5 3 

// A special case 
//            1 2 3 4
// number:62 11 6 6 2
// number:74  9 5 5 2
// number:169 8 5 5 2

// Rules
// 1. 6 or 5 is best played at 3rd away
// 2. At 2nd if we have 6 or 5 appear twice we can pick the game i.e same 6 or 5 player cannot play and loss 3 times from from first
// 3. At 4th we can play 4 
// 4. At 5th we can play 3 or 2

// first analysis if you place 6 or 5 in the second place you might likely not loss