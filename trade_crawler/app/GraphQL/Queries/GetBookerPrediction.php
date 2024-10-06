<?php

namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;


/**
 * desc: get the team booker's prediction count
 * 
 */


class GetBookerPrediction
{
  protected string $season_id = "2879263";
  protected array $matchDays = [[1, 15], [16, 30]];

  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  public function __invoke()
  {
    return collect($this->teams)->map(function ($team) {
      $count = collect($this->matchDays)->map(function ($matchDay) use ($team) {
        $count = OverOrUnder::where('season_id', $this->season_id)->where(function ($query) use ($team) {
          $query->where('home', $team)->orWhere('away', $team);
        })->whereBetween('matchday_id', $matchDay)->where('booker_prediction', '1')->count();
        return $count;
      });
      return ['team' => $team, 'counts' => [$count[0], $count[1]]];
    })->all();
  }
}
