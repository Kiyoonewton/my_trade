<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use App\Models\TeamArranger;
use App\Trait\TeamArrangerTrait as TraitTeamArrangerTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Cornelius
{
  use TraitTeamArrangerTrait;

  protected array $teams = ["Bournemouth", "Burnley", "Chelsea"];
  protected function arrangeTeam($num)
  {
    // Log::info('message',$this->getThreeTeams(1));
    $result = [];
    for ($i = 0; $i < 3; $i++) {
      for ($j = $i + 1; $j < 3; $j++) {
        $result[] =  [
          $this->getThreeTeams($num)[0][$i],
          $this->getThreeTeams($num)[0][$j]
        ];
      }
    }
    return ['matches' => $result];
  }

  protected function getThreeTeams($id)
  {
    return TeamArranger::where('id', $id)->get(['team1', 'team2', 'team3'])->map(function ($team) {
      return [$team->team1, $team->team2, $team->team3];
    })->all();
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
            $result = $item->result;
            return $result;
          });
      })->flatten(1)->all();
      return [
        'matches' => $matchResults,
        'count' => collect($matchResults)->count()
      ];
    });
  }
  public function getCount($total1, $total2)
  {
    $count = 0;
    for ($i = 0; $i < 6; $i++) {
      if ((collect($total1))->all()['matches'][$i] === (collect($total2))->all()['matches'][$i]) {
        $count++;
      }
    }
    return $count;
  }
  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];

    $num = 0;

    for ($j = $start; $j < $end; $j++) {
      for ($l = 1; $l < 561; $l++) {
        $team = $this->arrangeTeam($l);

        $total1 = $this->processMatches([$team], $this->getSeasonId($j + 1)->first(), [1, 30])->first();
        $total2 = $this->processMatches([$team], $this->getSeasonId($j + 2)->first(), [1, 30])->first();
        $total3 = $this->processMatches([$team], $this->getSeasonId($j + 3)->first(), [1, 30])->first();

        $count = $this->getCount($total1, $total2);
        if ($count === 6) {
          $count1 = $this->getCount($total2, $total3);
          echo "season $j, " ."team $l " .  ($count1 === 6 ? '6 ==========> Loss' : '6 ==========> Win') . ($count1 === 0 ? ', 0 ==========> Loss' : ', 0 ==========> Win') . "\n";
        } elseif ($count === 0) {
          $count2 = $this->getCount($total2, $total3);
          echo "season $j, " . "team $l " .  ($count2 === 6 ? '6 ==========> Loss' : '6 ==========> Win') . ($count2 === 0 ? ', 0 ==========> Loss' : ', 0 ==========> Win') . "\n";
        } else {
          echo "season $j, " . "team $l " .  " $count" . "\n";
        }
        $count = 0;
      }
    }
    // return $this->getThreeTeams(1);
  }
}
