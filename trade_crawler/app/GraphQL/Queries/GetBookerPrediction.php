<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

/**
 * desc: get the team booker's prediction count
 * 
 */

class GetBookerPrediction
{
  protected string $season_id = "2879263";
  protected array $matchDays = [[1, 15], [16, 30]];

  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  /**
   * desc: get the team that played according to the booker
   * Return: Array [team1, team2, number_of_their_won_to_the_prediction]
   */

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
        'matches' => $matches->all(),
        'count' => $matches->count()
      ];
    });
  }

  protected function getSeasonId(int $start, int $end)
  {
    return DB::table(DB::raw("(SELECT *, @row_num := @row_num + 1 AS row_num 
    FROM seasons, (SELECT @row_num := 0) AS init 
    ORDER BY seasonId) AS subquery"))
      ->whereBetween('row_num', [$start, $end])
      ->pluck('seasonId')
      ->toArray();
  }

  public function __invoke($_, $args)
  {
    $type = $args['type'];
    $start = $args['start'];
    $end = $args['end'];

    $seasons = $this->getSeasonId($start, $end);
    $seasonLength = count($seasons);

    $allResults = [];

    for ($i = 0; $i < $seasonLength; $i++) {
      $season_id = $seasons[$i];

      for ($j = 0; $j < count($this->matchDays); $j++) {
        $matches = [];
        $matchDays_array = [];

        if ($i === 0 && $j === 0) {
          $matches = $this->getTeams(0, $type, $seasons[$i]);
          $matchDays_array = $this->matchDays[1];
        }
        if ($i !== 0 && $j === 0) {
          $matches = $this->getTeams(1, $type, $seasons[$i - 1]);
          $matchDays_array = $this->matchDays[0];
        }
        if ($i !== 0 && $j === 1) {
          $matches = $this->getTeams(0, $type, $seasons[$i]);
          $matchDays_array = $this->matchDays[1];
        }

        //return ['seasons' => $seasons, 'seasons2' => $seasons[$i - 1], 'season' => $season_id, 'matchDays_array' => $matchDays_array, 'matches' => $matches, 'other' => [$i, $j]];

        $results = collect($matches)->map(function ($matchSet) use ($type, $season_id, $matchDays_array) {
          $matchResults = collect($matchSet['matches'])->map(function ($match) use ($season_id, $matchDays_array) {
            return OverOrUnder::where('season_id', $season_id)
              ->whereBetween('matchday_id', $matchDays_array)
              ->whereIn('home', [$match[0], $match[1]])
              ->whereIn('away', [$match[0], $match[1]])
              // ->get(['home', 'away', 'result'])
              // ->map(function ($item) {
              // return $item->toArray();        // Convert each model instance to an array
              // });
              // })->flatten(1)->all();
              ->pluck('result')->first();
          });

          $success = $matchResults->contains($type) ? 'success' : 'fail';

          return ['match' => $success, 'count' => $matchSet['count']];
        });

        $allResults[] = ['result' => $results->all(), 'number' => $i + $start];
      }
    }

    return $allResults;
  }
}
