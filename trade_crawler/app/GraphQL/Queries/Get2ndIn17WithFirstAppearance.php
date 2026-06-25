<?php

namespace App\GraphQL\Queries;

use App\Models\WinOrDraw;
use App\Models\Season;

/**
 * desc: get the team booker's prediction count
 * 
 */

class Get2ndIn17WithFirstAppearance
{
  // protected string $season_id = "3054200";
  protected array $matchDays = [1, 30];
  protected ?float $oddTarget = null;
  protected float $oddRange = 0.1;

  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  /**
   * Process a season and return analysis
   * @param string $seasonId
   * @param float|null $oddTarget - e.g. 1.5 to filter matches with odds between 1.4 and 1.6
   * @param float $oddRange - range for filtering (default 0.1)
   */
  public function process(string $seasonId, ?float $oddTarget = null, float $oddRange = 0.1)
  {
    // Step 1: Fetch and save data from API
    $job = new \App\Jobs\ProcessWinOrDraw($seasonId);
    $job->handle();

    // Step 2: Run analysis
    return $this->__invoke(null, ['seasonId' => $seasonId, 'oddTarget' => $oddTarget, 'oddRange' => $oddRange]);
  }

  /**
   * desc: get the team that played according to the booker
   * Return: Array [team1, team2, number_of_their_won_to_the_prediction]
   */

  protected function getTeams()
  {
    return collect($this->teams)->map(function ($team) {
      $results = WinOrDraw::where('season_id', $this->season_id)->where(function ($query) use ($team) {
        $query->where('home', $team)->orWhere('away', $team);
      })->whereBetween('matchday_id', $this->matchDays)->get(['home', 'away', 'matchday_id', 'home_odd', 'away_odd', 'draw_odd', 'result']);

      $matches = $results->map(function ($match) use ($team) {
        $position = $match->home === $team ? 'home' : 'away';
        $odd = (float) ($position === 'home' ? $match->home_odd : $match->away_odd);

        $win = ($position === 'home' && $match->result === '1') || ($position === 'away' && $match->result === '2');

        return [
          'position' => $position,
          'odd' => $odd,
          'win' => $win,
          'result' => $match->result,
          'matchday_id' => $match->matchday_id,
          'home' => $match->home,
          'away' => $match->away,
        ];
      });

      // Filter by oddTarget if set
      if ($this->oddTarget !== null) {
        $minOdd = $this->oddTarget - $this->oddRange;
        $maxOdd = $this->oddTarget + $this->oddRange;
        $matches = $matches->filter(function ($match) use ($minOdd, $maxOdd) {
          return $match['odd'] >= 0 && $match['odd'] <= 1.75;
        });
      }

      return ['team' => $team, 'matches'=>$matches, 'count' => $matches->count()];
    });
  }

  public function __invoke($_, $args = [])
  {
    $seasonId = $args['seasonId'];
    $this->oddTarget = $args['oddTarget'] ?? null;
    $this->oddRange = $args['oddRange'] ?? 0.1;
    $this->season_id = $seasonId;

    $allResults = [];

    $teams = $this->getTeams()
      ->sortByDesc('count')
      ->filter(function ($team) {
        return $team['count'] > 4;
      })
      ->map(function ($team) {
        // Check if any of the first 5 matches is a win
        // $first5 = collect($team['matches'])->take(4);
        // $hasWin = $first5->contains('win', true);

          $firstThreeAreFalse = $team['matches']->take(2)->every(function ($match) {
    return $match['win'] === false;
});
        return [
          'team' => $team['team'],
          'count'=>$team['count'],
          // 'matches'=> ($team['matches'])->take(3),
          '2fails' => $firstThreeAreFalse,
          '3rd' => $firstThreeAreFalse ? ($team['matches'])->skip(2)->first()
 : ""
          // 'win' => $hasWin,
        ];
      })
      ->values();
return $teams;
  }
}
