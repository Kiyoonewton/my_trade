<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use Illuminate\Support\Facades\DB;

/**
 * desc: get the team booker's prediction count
 * 
 */

class Get11or12ThatPlayTheSame
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


  /**
   * The function `processMatches` processes a collection of matches based on certain criteria and
   * returns an array indicating the success or failure of each match.
   * 
   * @param matches The `matches` parameter is an array containing sets of matches. Each match set
   * contains an array of matches where each match is represented by an array with two elements - the
   * home team and the away team.
   * @param type The `type` parameter in the `processMatches` function represents the type of match
   * result you are looking for, such as 'over', 'under', or any other specific outcome. This parameter
   * is used to filter and determine the success or failure of each match based on the specified type
   * of result.
   * @param seasons Seasons is a variable that likely represents the season or seasons for which the
   * matches are being processed. It is used in the function to filter the matches based on the
   * specified season or seasons.
   * @param matchDays_array The `matchDays_array` parameter is an array containing the match day IDs
   * for filtering the matches. It is used in the `whereBetween` clause to filter matches based on the
   * range of match day IDs specified in the array.
   * 
   * @return An array of match results with their corresponding success status ('success' or 'fail')
   * and count for each match set.
   */
  public function processMatches($matches, $type, $seasons, $matchDays_array)
  {
    return collect($matches)->map(function ($matchSet) use ($type, $seasons, $matchDays_array) {
      $matchResults = collect($matchSet['matches'])->map(function ($match) use ($seasons, $matchDays_array) {
        return OverOrUnder::where('season_id', $seasons)
          ->whereBetween('matchday_id', $matchDays_array)
          ->whereIn('home', [$match[0], $match[1]])
          ->whereIn('away', [$match[0], $match[1]])
          ->pluck('result')->first();
      });

      $success = $matchResults->contains($type) ? 'success' : 'fail';

      return ['match' => $success, 'count' => $matchSet['count']];
    });
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
      // $season_id = $seasons[$i];

      for ($j = 0; $j < count($this->matchDays); $j++) {
        $matches = [];
        $matchDays_array = [];

        if ($i === 0 && $j === 0) {
          $matches = $this->getTeams($j, $type, $seasons[$i]);
          // $matchDays_array = $this->matchDays[1];
        }

        if ($j === 1) {
          $matches = $this->getTeams(0, $type, $seasons[$i]);
          $matchDays_array = $this->matchDays[1];
        }

        if ($i !== 0 && $j === 0) {
          $matches = $this->getTeams($j, $type, $seasons[$i]);
          $matchDays_array = $this->matchDays[1];
        }
        // if ($i !== 0 && $j === 0) {
        //   $matches = $this->getTeams(0, $type, $seasons[$i]);
        //   $matchDays_array = $this->matchDays[1];
        // }
        // if ($i !== 0 && $j === 1) {
        //   $matches = $this->getTeams(1, $type, $seasons[$i + 1]);
        //   $matchDays_array = $this->matchDays[0];
        // }

        //return ['seasons' => $seasons, 'seasons2' => $seasons[$i - 1], 'season' => $season_id, 'matchDays_array' => $matchDays_array, 'matches' => $matches, 'other' => [$i, $j]];

        if ($i !== 0 && $j === 0) {
          $results = $this->processMatches($matches, $type, $seasons[$i], $matchDays_array);
        } else if ($i === 0 && $j === 1) {
          $results =  $this->processMatches($matches, $type, $seasons[$i], $matchDays_array);
        } else {
          $results = [];
        }

        $allResults[] = ['result' => $results, 'number' => $i + $start];
      }
    }

    return $allResults;
  }
}
