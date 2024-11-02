<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use App\Models\Result;
use App\Models\TeamArranger;
use App\Trait\TeamArrangerTrait as TraitTeamArrangerTrait;
use Illuminate\Support\Facades\DB;

use function Illuminate\Log\log;

class Cornelius3
{
  use TraitTeamArrangerTrait;

  protected array $teams = ["Bournemouth", "Burnley", "Chelsea"];
  protected function arrangeTeam($num)
  {
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

  protected function processMatches($matches, $seasons, $matchDays_array)
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
            $matchday_id = $item->matchday_id;
            $sameOdd = $result === 1 ? $item->under : $item->over;
            $againstOdd = $result === 1 ? $item->over : $item->under;
            return [$result, $sameOdd, $againstOdd, $matchday_id];
          });
      })->flatten(1)->all();
      return [
        'matches' => $matchResults,
        'count' => collect($matchResults)->count()
      ];
    });
  }
  protected function getCountOddAndMatchday($total1, $total2)
  {
    $count = 0;
    $odd = [];
    $matchday = [];
    for ($i = 0; $i < 6; $i++) {
      if ((collect($total1))->all()['matches'][$i][0] === (collect($total2))->all()['matches'][$i][0]) {
        $count++;
      }
      $sameOdd[] = (collect($total2))->all()['matches'][$i][1];
      $againstOdd[] = (collect($total2))->all()['matches'][$i][2];
      $matchday[] = (collect($total2))->all()['matches'][$i][3];
      $result[] = (collect($total2))->all()['matches'][$i][0];
    }
    return [$count, $sameOdd, $againstOdd, $matchday, $result];
  }

  protected function getTotal($team, $j, $num)
  {
    return $this->processMatches([$team], $this->getSeasonId($j + $num)->first(), [1, 30])->first();
  }

  protected function getCount($team, $j, $num)
  {
    return $this->getCountOddAndMatchday($this->getTotal($team, $j, $num), $this->getTotal($team, $j, $num + 1));
  }

  protected function getFinalOutcome($outcome1, $outcome2, $outcome3, $outcome4, $team, $j)
  {
    switch (true) {
      case $outcome1 === 'Win':
        return $this->getCount($team, $j, 3)[0] === 6 ? 'Loss' : 'Win';
      case $outcome2 === 'Win':
        return $this->getCount($team, $j, 4)[0] === 6 ? 'Loss' : 'Win';
      case $outcome3 === 'Win':
        return $this->getCount($team, $j, 5)[0] === 6 ? 'Loss' : 'Win';
      case $outcome4 === 'Win':
        return $this->getCount($team, $j, 5)[0] === 6 ? 'Loss' : 'Win';
      default:
        return 'Loss';
    }
  }
  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];

    for ($j = $start; $j < $end; $j++) {
      $seasonId = $this->getSeasonId($j + 1)->first();
      for ($l = 1; $l < 561; $l++) {
        $team = $this->arrangeTeam($l);
        $team1 = $team['matches'][0][0];
        $team2 = $team['matches'][0][1];
        $team3 = $team['matches'][1][1];

        if ($this->getCount($team, $j, 1)[0] === 6) {
          $sameOdd1 = implode(",", $this->getCount($team, $j, 1)[1]);
          $matchday1 = implode(",", $this->getCount($team, $j, 1)[3]);
          $result1 = implode(",", $this->getCount($team, $j, 1)[4]);
          $outcome1 = $this->getCount($team, $j, 2)[0] === 6 ? 'Loss' : 'Win';
          $outcome2 = $outcome1 === 'Loss'  ? ($this->getCount($team, $j, 3)[0] === 6 ? 'Loss' : 'Win') : "";
          $outcome3 = $outcome2 === 'Loss'  ? ($this->getCount($team, $j, 4)[0] === 6 ? 'Loss' : 'Win') : "";
          $outcome4 = $outcome3 === 'Loss'  ? ($this->getCount($team, $j, 5)[0] === 6 ? 'Loss' : 'Win') : "";
          $final_outcome = $this->getFinalOutcome($outcome1, $outcome2, $outcome3, $outcome4, $team, $j);
          Result::create([
            'num' => $j + 1,
            'season_id' => $seasonId,
            "team_num" => $l,
            'team1' => $team1,
            'team2' => $team2,
            'team3' => $team3,
            'type' => "same",
            'matchday' => $matchday1,
            'outcome1' => $outcome1,
            'outcome2' => $outcome2,
            'outcome3' => $outcome3,
            'outcome4' => $outcome4,
            'final_outcome' => $final_outcome,
            'odd' => $sameOdd1,
            'result' => $result1
          ]);
          echo "season $j, " . "team $l " . "-------> got one" . "\n";
          // }
          //  elseif ($count === 0) {
          // $oddAndMatchday1 = $this->getCountOddAndMatchday($total2, $total3);
          // // $count1 = $oddAndMatchday1[0];
          // $againstOdd1 = implode(",", $oddAndMatchday1[1]);
          // $matchday1 = implode(",", $oddAndMatchday1[3]);
          // $result1 = implode(",", $oddAndMatchday1[4]);
          // Result::create(['num' => $j + 1, 'season_id' => $seasonId, "team_num" => $l, 'team1' => $team1, 'team2' => $team2, 'team3' => $team3, 'type' => "against", 'matchday' => $matchday1, 'outcome1' => $count1 === 0 ? 'Loss' : 'Win', 'outcome2' => $count1 === 0  ? ($count2 === 0 ? 'Loss' : 'Win') : "", 'odd' => $againstOdd1, 'result' => $result1]);
          // // echo "season $seasonId, " . "team $team1 vs $team2, $team1 vs $team3, $team2 vs $team3 " . "type same" . " matchday $matchday1" . " same_outcome " . ($count1 === 6 ? '6 ==========> Loss' : '6 ==========> Win') . " againt_outcome " . ($count1 === 0 ? ', 0 ==========> Loss' : ', 0 ==========> Win') . "same_odd $sameOdd1 ". "against_odd $againtOdd1" . "\n";
          // echo "season $j, " . "team $l " .  " $count" . "-------> got one" . "\n";
        } else {
          echo "season $j, " . "team $l " . "\n";
        }
        $count = 0;
      }
    }
  }
}

//team team1 team2 team3
//pick odd "1.60 1.5 "