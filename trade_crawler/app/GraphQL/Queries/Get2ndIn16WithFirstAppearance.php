<?php

namespace App\GraphQL\Queries;

use App\Models\WinOrDraw;
use App\Models\Season;

/**
 * desc: get the team booker's prediction count
 * 
 */

class Get2ndIn16WithFirstAppearance
{
  protected string $season_id = "3054200";
  protected array $matchDays = [1, 30];

  protected array $teams = ['Bournemouth', 'Burnley', 'Chelsea', 'Crystal Palace', 'Everton', 'Leicester', 'Liverpool', 'London Guns', 'Manchester Blue', 'Manchester Reds', 'Newcastle', 'Southampton', 'Tottenham', 'Watford', 'West Ham', 'Wolverhampton'];

  /**
   * Process a season and return analysis
   * @param string $seasonId
   * @param array|null $teamIndices - e.g. [1,2,3] for teams at positions 1,2,3 (1-indexed)
   */
  public function process(string $seasonId, ?array $teamIndices = null)
  {
    // Step 1: Fetch and save data from API
    $job = new \App\Jobs\ProcessWinOrDraw($seasonId);
    $job->handle();

    // Step 2: Run analysis
    return $this->__invoke(null, ['seasonId' => $seasonId, 'teamIndices' => $teamIndices]);
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
        $odd = $position === 'home' ? $match->home_odd : $match->away_odd;

        $win = ($position === 'home' && $match->result === '1') || ($position === 'away' && $match->result === '2');

        return [
          'position' => $position,
          'odd' => $odd,
          'win' => $win,
          'result' => $match->result,
        ];
      });

      $total_odd = $matches->reduce(function ($carry, $match) {
        return $carry + (float) $match['odd'];
      }, 1.0);

      return ['team' => $team, 'matches' => $matches->values()->all(), 'total_odd' => $total_odd];
    });
  }

  public function __invoke($_, $args = [])
  {
    $seasonId = $args['seasonId'] ?? $this->season_id;
    $teamIndices = $args['teamIndices'] ?? null;
    $this->season_id = $seasonId;

    $allResults = [];

    $winPatterns = [
      // ['1', '1', '1'],
      ['1', '1', 'x'],
      ['1', 'x', '1'],
      ['x', '1', '1'],
      ['1', '1', '2'],
      ['1', '2', '1'],
      ['2', '1', '1'],
      ['1', 'x', 'x'],
      ['x', '1', 'x'],
      ['x', 'x', '1'],
      ['1', '2', '2'],
      ['2', '1', '2'],
      ['2', '2', '1'],
    ];

    $teams = $this->getTeams()->sortBy('total_odd')->values();

    // If specific indices provided, use only those teams
    if ($teamIndices !== null) {
      $top3 = collect($teamIndices)->map(function ($index) use ($teams) {
        return $teams[$index - 1]; // Convert 1-indexed to 0-indexed
      })->values()->all();
      $teamCombinations = [$top3];
    } else {
      // Generate all combinations of 3 teams from top 6
      $top6 = $teams->take(6);
      $teamCombinations = [];

      for ($i = 0; $i < count($top6); $i++) {
        for ($j = $i + 1; $j < count($top6); $j++) {
          for ($k = $j + 1; $k < count($top6); $k++) {
            $teamCombinations[] = [$top6[$i], $top6[$j], $top6[$k]];
          }
        }
      }
    }

    $allResults = [];

    foreach ($teamCombinations as $index => $top3) {
      $matchCount = count($top3[0]['matches']);
      $allComparisons = [];

      for ($i = 0; $i < $matchCount; $i++) {
        $combo = [
          $top3[0]['matches'][$i]['result'] ?? null,
          $top3[1]['matches'][$i]['result'] ?? null,
          $top3[2]['matches'][$i]['result'] ?? null,
        ];
        $allComparisons[] = [
          'results' => $combo,
          'win' => in_array($combo, $winPatterns),
        ];
      }

      $windows = [];
      for ($i = 0; $i <= $matchCount - 4; $i++) {
        $window = array_slice($allComparisons, $i, 4);
        $hasWin = collect($window)->contains('win', true);
        $windows[] = [
          'range' => ($i + 1) . '-' . ($i + 4),
          'win' => $hasWin,
        ];
      }

      $allResults[] = [
        'combination' => [$top3[0]['team'], $top3[1]['team'], $top3[2]['team']],
        'windows' => $windows,
      ];
    }

    return $allResults;
  }
}
