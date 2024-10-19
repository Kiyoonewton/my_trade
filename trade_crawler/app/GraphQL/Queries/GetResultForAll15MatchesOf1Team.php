<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

class GetResultForAll15MatchesOf1Team
{
  protected array $matchDays = [[1, 15], [16, 30]];

  // protected array $teams = ['Manchester Blue'];
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
        $query->where('home', $team)->orWhere('away', $team);
      })->whereBetween('matchday_id', $this->matchDays[$index])->where('booker_prediction', $type)->get(['home', 'away', 'matchday_id']);

      $matches = $results->map(function ($match) {
        return [$match->home, $match->away, $match->matchday_id];
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
          ->whereIn('home', [$match[0], $match[1]])
          ->whereIn('away', [$match[0], $match[1]])
          ->where('booker_prediction', $type)
          // ->pluck('home')->first();
          ->get(['home', 'away', 'matchday_id'])
          ->map(function ($item) {

            $home = $item->home;
            $away = $item->away;
            $matchday_id = $item->matchday_id;
            return [$home, $away, $matchday_id];        // Convert each model instance to an array
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
    // $team_index = $args['team_index'];
    $total = [];
    for ($j = $start; $j < $end; $j++) {
      $teams = $this->getTeams(0, 0, $this->getSeasonId($j + 1)->first());
      $nextGame1 = $this->processMatches($teams, 0, $this->getSeasonId($j + 1)->first(), $this->matchDays[1],);
      $nextGame2 = $this->processMatches($nextGame1, 0, $this->getSeasonId($j + 2)->first(), $this->matchDays[0],);
      $nextGame3 = $this->processMatches($nextGame2, 0, $this->getSeasonId($j + 2)->first(), $this->matchDays[1],);
      $nextGame4 = $this->processMatches($nextGame3, 0, $this->getSeasonId($j + 3)->first(), $this->matchDays[0],);
      $nextGame5 = $this->processMatches($nextGame4, 0, $this->getSeasonId($j + 3)->first(), $this->matchDays[1],);
      $nextGame6 = $this->processMatches($nextGame5, 0, $this->getSeasonId($j + 4)->first(), $this->matchDays[0],);
      $nextGame7 = $this->processMatches($nextGame6, 0, $this->getSeasonId($j + 4)->first(), $this->matchDays[1],);
      $nextGame8 = $this->processMatches($nextGame7, 0, $this->getSeasonId($j + 5)->first(), $this->matchDays[0],);

      // return ['first' => $teams->all(), 'second' => $nextGame1->all(), 'third' => $nextGame2->all(), 'fourth' => $nextGame3->all()];
      // for ($i = 0; $i < 1; $i++) {

      // }
      $result = [];

      for ($i = 0; $i < 16; $i++) {
        $result[] = [
          // 'third' => $nextGame2[$i]['count'],
          // 'fourth' => $nextGame3[$i]['count'],
          'fifth' => $nextGame4[$i]['count'],
          'sixth' => $nextGame5[$i]['count'],
          'seven' => $nextGame6[$i]['count'],
          'eight' => $nextGame7[$i]['count'],
        ];
      }

      $total[] = ['result' => $result, 'number' => $j + 1, 'season' => $this->getSeasonId($j + 1)->first()];
    }
    return $total;
  }
}

//2881020 69 4,2,2,2,1
//2880865 63 3,2,2,2,1