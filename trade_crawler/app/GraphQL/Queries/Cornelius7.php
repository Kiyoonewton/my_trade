<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use App\Models\Result;
use App\Models\TeamArranger;
use App\Trait\TeamArrangerTrait as TraitTeamArrangerTrait;
use Illuminate\Support\Facades\DB;

use function Illuminate\Log\log;

class Cornelius7
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
          ->get(['home', 'away', 'matchday_id', 'over', 'under', 'booker_prediction'])
          ->map(function ($item) {
            // print_r($item->matchday_id);
            $result = $item->booker_prediction;
            $matchday_id = $item->matchday_id;
            $overOdd = $item->over;
            $underOdd = $item->under;
            return [$result, $overOdd, $underOdd, $matchday_id];
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
    $matchday = [];
    for ($i = 0; $i < 6; $i++) {
      if ((collect($total1))->all()['matches'][$i][0] === 1 && (collect($total1))->all()['matches'][$i][0] === (collect($total2))->all()['matches'][$i][0]) {
        $count++;
      }
      $overOdd[] = (collect(value: $total1))->all()['matches'][$i][1] > (collect($total1))->all()['matches'][$i][2] ? (collect($total1))->all()['matches'][$i][1] : (collect($total1))->all()['matches'][$i][2];
      $matchday[] = (collect($total2))->all()['matches'][$i][3];
      $result[] = (collect($total2))->all()['matches'][$i][0];
    }
    return [$count, $overOdd, $matchday, $result];
  }

  protected function getOneCountOddAndMatchday($total1)
  {
    $count = 0;
    $matchday = [];
    for ($i = 0; $i < 6; $i++) {
      if ((collect($total1))->all()['matches'][$i][0] === 1) {
        $count++;
      }
      $overOdd[] = (collect(value: $total1))->all()['matches'][$i][1] > (collect($total1))->all()['matches'][$i][2] ? (collect($total1))->all()['matches'][$i][1] : (collect($total1))->all()['matches'][$i][2];
      $matchday[] = (collect($total1))->all()['matches'][$i][3];
      $result[] = (collect($total1))->all()['matches'][$i][0];
    }
    return [$count, $overOdd, $matchday, $result];
  }
  protected function getTotal($team, $j, $num)
  {
    echo $num;
    return $this->processMatches([$team], $this->getSeasonId($j + $num)->first(), [1, 30])->first();
  }

  protected function getCount($team, $j, $num)
  {
    return $this->getCountOddAndMatchday($this->getTotal($team, $j, $num), $this->getTotal($team, $j, $num + 1));
  }
  protected function getOneCount($team, $j, $num)
  {
    print_r($this->getOneCountOddAndMatchday($this->getTotal($team, $j, $num)));
    return $this->getOneCountOddAndMatchday($this->getTotal($team, $j, $num));
  }

  protected function getFinalOutcome($outcome1, $outcome2, $outcome3, $outcome4, $team, $j, $first)
  {
    switch (true) {
      case $outcome1 === 'Win':
        return $first ? ($this->getOneCount($team, $j, 3)[0] === 6 ? 'Loss' : 'Win') : ($this->getOneCount($team, $j, 3)[0] === 0 ? 'Loss' : 'Win');
      case $outcome2 === 'Win':
        return $first ? ($this->getOneCount($team, $j, 4)[0] === 6 ? 'Loss' : 'Win') : ($this->getOneCount($team, $j, 4)[0] === 0 ? 'Loss' : 'Win');
      case $outcome3 === 'Win':
        return $first ? ($this->getOneCount($team, $j, 5)[0] === 6 ? 'Loss' : 'Win') : ($this->getOneCount($team, $j, 5)[0] === 0 ? 'Loss' : 'Win');
      case $outcome4 === 'Win':
        return $first ? ($this->getOneCount($team, $j, 6)[0] === 6 ? 'Loss' : 'Win') : ($this->getOneCount($team, $j, 5)[0] === 6 ? 'Loss' : 'Win');
      default:
        return 'Loss';
    }
  }

  protected function getFinalOutcome2($outcome1, $outcome2, $outcome3, $outcome4, $team, $j, $first)
  {
    return $first ? ($this->getCount($team, $j, $this->getWinOutcome($outcome1, $outcome2, $outcome3, $outcome4,))[0] === 6 ? 'Loss' : 'Win') : ($this->getCount($team, $j, 4)[0] === 0 ? 'Loss' : 'Win');
  }

  protected function getWinOutcome($outcome1, $outcome2, $outcome3, $outcome4,)
  {
    switch (true) {
      case $outcome1 === 'Win':
        return 2;
      case $outcome2 === 'Win':
        return 3;
      case $outcome3 === 'Win':
        return 4;
      case $outcome4 === 'Win':
        return 5;
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
      for ($l = 187; $l < 561; $l++) {
        $team = $this->arrangeTeam($l);

        if ($this->getOneCount($team, $j, 1)[0] === 6) {
          $team1 = $team['matches'][0][0];
          $team2 = $team['matches'][0][1];
          $team3 = $team['matches'][1][1];
          $outcome0 = $this->getOneCount($team, $j, 1)[0];
          $outcome1 = $this->getCount($team, $j, 1)[0] === 6 ? 'Loss' : 'Win';
          $outcome2 = $outcome1 === 'Loss'  ? ($this->getCount($team, $j, 2)[0] === 6 ? 'Loss' : 'Win') : "";
          $outcome3 = $outcome2 === 'Loss'  ? ($this->getCount($team, $j, 3)[0] === 6 ? 'Loss' : 'Win') : "";
          $outcome4 = $outcome3 === 'Loss'  ? ($this->getCount($team, $j, 4)[0] === 6 ? 'Loss' : 'Win') : "";
          $winOutcome = $this->getWinOutcome($outcome1, $outcome2, $outcome3, $outcome4);
          $final_outcome1 = $this->getFinalOutcome($outcome1, $outcome2, $outcome3, $outcome4, $team, $j, true);
          $final_outcome2 = $final_outcome1 === 'Loss' ? $this->getFinalOutcome2($outcome1, $outcome2, $outcome3, $outcome4, $team, $j, true) : "";
          // $sameOdd1 = implode(",", $this->getOneCount($team, $j, $final_outcome1 === 'Loss' ? $winOutcome + 2 : $winOutcome + 1)[1]);
          // $matchday1 = implode(",", $this->getOneCount($team, $j, $final_outcome1 === 'Loss' ? $winOutcome + 2 : $winOutcome + 1)[2]);
          $result1 = implode(",", $this->getOneCount($team, $j, 6)[3]);
          return($result1);
          Result::where('num', $j + 1)->where('team_num', $l)->update([
            'num' => $j + 1,
            'season_id' => $seasonId,
            "team_num" => $l,
            'team1' => $team1,
            'team2' => $team2,
            'team3' => $team3,
            'type' => "same",
            'matchday' => '$matchday1',
            'outcome0' => $outcome0,
            'outcome1' => $outcome1,
            'outcome2' => $outcome2,
            'outcome3' => $outcome3,
            'outcome4' => $outcome4,
            'final_outcome1' => $final_outcome1,
            'final_outcome2' => $final_outcome1 ===  'Win' ? "" : $final_outcome2,
            'odd' => '$sameOdd1',
            'result' => $result1
          ]);
        } else {
          echo "season ($j+1), " . "team $l " . "\n";
        }
      }
    }
  }
}

//team team1 team2 team3
//pick odd "1.60 1.5 "