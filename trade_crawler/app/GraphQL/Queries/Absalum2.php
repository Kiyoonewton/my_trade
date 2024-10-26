<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class Absalum2
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

  protected function getTeams(int $index, string $seasonId)
  {
    return collect($this->teams)->map(function ($team) use ($index, $seasonId) {
      $results = OverOrUnder::where('season_id', $seasonId)->where(function ($query) use ($team) {
        $query->where('home', $team);
      })->whereBetween('matchday_id', $this->matchDays[$index])->where('booker_prediction', 1)->get(['home', 'away', 'over', 'under', 'matchday_id']);

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

  public function processMatches($matches, $seasons, $matchDays_array)
  {
    return collect($matches)->map(function ($matchSet) use ($seasons, $matchDays_array) {
      $matchResults = collect($matchSet['matches'])->map(function ($match) use ($seasons, $matchDays_array) {
        return OverOrUnder::where('season_id', $seasons)
          ->whereBetween('matchday_id', $matchDays_array)
          ->where('home', $match[0])
          ->where('away', $match[1])
          ->where('booker_prediction', 1)
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

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];
    $total = [];

    for ($j = $start; $j < $end; $j++) {
      $nextGame1 = $this->getTeams(0, $this->getSeasonId($j + 1)->first());
      $nextGame2 = $this->processMatches($nextGame1, $this->getSeasonId($j + 2)->first(), $this->matchDays[0]);
      $nextGame3 = $this->processMatches($nextGame2, $this->getSeasonId($j + 3)->first(), $this->matchDays[0]);
      $nextGame4 = $this->processMatches($nextGame3, $this->getSeasonId($j + 4)->first(), $this->matchDays[0],);
      $nextGame5 = $this->processMatches($nextGame4, $this->getSeasonId($j + 5)->first(), $this->matchDays[0],);
      // $nextGame6 = $this->processMatches($nextGame5, $this->getSeasonId($j + 6)->first(), $this->matchDays[0],);
      // $nextGame7 = $this->processMatches($nextGame6, $this->getSeasonId($j + 7)->first(), $this->matchDays[0],);
      // $nextGame8 = $this->processMatches($nextGame7, $this->getSeasonId($j + 8)->first(), $this->matchDays[0],);
      // $nextGame9 = $this->processMatches($nextGame8, $this->getSeasonId($j + 9)->first(), $this->matchDays[0],);
      // $nextGame10 = $this->processMatches($nextGame9, $this->getSeasonId($j + 10)->first(), $this->matchDays[0],);
      // $nextGame11 = $this->processMatches($nextGame10, $this->getSeasonId($j + 11)->first(), $this->matchDays[0],);
      // $nextGame12 = $this->processMatches($nextGame11, $this->getSeasonId($j + 12)->first(), $this->matchDays[0],);
      // $nextGame13 = $this->processMatches($nextGame12, $this->getSeasonId($j + 13)->first(), $this->matchDays[0],);

      $result = [];

      for ($i = 0; $i < 16; $i++) {
        if (
          ($nextGame1[$i]['count'] === 6 && $nextGame2[$i]['count'] === 6 && $nextGame3[$i]['count'] === 6)
          || ($nextGame2[$i]['count'] === 6 && $nextGame3[$i]['count'] === 6 && $nextGame4[$i]['count'] === 6) ||
          ($nextGame3[$i]['count'] === 6 && $nextGame4[$i]['count'] === 6 && $nextGame5[$i]['count'] === 6)
          // ||
          // ($nextGame4[$i]['count'] === 6 && $nextGame5[$i]['count'] === 6)
          // ||
          // ($nextGame1[$i]['count'] === 5 && $nextGame2[$i]['count'] === 5)
          // || ($nextGame2[$i]['count'] === 5 && $nextGame3[$i]['count'] === 5) ||
          // ($nextGame3[$i]['count'] === 5 && $nextGame4[$i]['count'] === 5) ||
          // ($nextGame4[$i]['count'] === 5 && $nextGame5[$i]['count'] === 5)
          // $nextGame5[$i]['count'] === 2 &&
          // $nextGame2[$i]['count'] === 2)
          //   || ($nextGame6[$i]['count'] === 2 &&
          //     $nextGame7[$i]['count'] === 2 &&
          //     $nextGame4[$i]['count'] === 2 &&
          //     $nextGame5[$i]['count'] === 2)
          //   // || $nextGame4[$i]['count'] === 2
        ) {
          $result[] = [
            'first' => $nextGame1[$i]['count'],
            'second' => $nextGame2[$i]['count'],
            'third' => $nextGame3[$i]['count'],
            'fourth' => $nextGame4[$i]['count'],
            'fifth' => $nextGame5[$i]['count'],
            // 'sixth' => $nextGame6[$i]['count'],
            // 'seventh' => $nextGame7[$i]['count'],
            // 'eight' => $nextGame8[$i]['count'],
            // 'nine' => $nextGame9[$i]['count'],
            // 'ten' => $nextGame10[$i]['count'],
            // 'eleven' => $nextGame11[$i]['count'],
            // 'twelve' => $nextGame12[$i]['count'],
            // 'thirteen' => $nextGame13[$i]['count'],
          ];
          print_r($result);
          // } else {
        }
      }
      $total[] = $result;
      print_r($j + 1 . "\n");

      // Add current result to total array
      // $total[] = ['result' => $result, 'number' => $j + 1, 'season' => $this->getSeasonId($j + 1)->first()];

      // Output the result after each loop iteration
      // print_r($total); // or var_dump($total) for more detailed output
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
// 1. 12 and 11 is a valid game to play at 1
// 2. if number 1 and 2 are the same from 12 to 3 they are good
// 3. At 4th we can play 4 
// 4. At 5th we can play 3 or 2

// first analysis if you place 6 or 5 in the second place you might likely not loss