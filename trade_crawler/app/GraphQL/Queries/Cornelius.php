<?php


namespace App\GraphQL\Queries;

use App\Models\OddOrEven;
use App\Models\OverOrUnder;
use App\Models\Result;
use App\Models\TeamArranger;
use App\Trait\TeamArrangerTrait as TraitTeamArrangerTrait;
use Illuminate\Support\Facades\DB;

use function Illuminate\Log\log;

class Cornelius
{
  use TraitTeamArrangerTrait;

  // protected array $teams = ["Bournemouth", "Burnley", "Chelsea"];
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
        return OddOrEven::where('season_id', $seasons)
          ->whereBetween('matchday_id', $matchDays_array)->where(function ($query) use ($match) {
            $query->where('home', $match[0])->orWhere('home', $match[1]);
          })->where(function ($query) use ($match) {
            $query->where('away', $match[0])->orWhere('away', $match[1]);
          })->get(['home', 'away', 'matchday_id', 'odd', 'even', 'result', 'home', 'away', 'season_id'])
          ->map(function ($item) {
            $result = $item->result;
            $matchday_id = $item->matchday_id;
            $oddOdd = $item->odd;
            $evenOdd = $item->even;
            $home = $item->home;
            $away = $item->away;
            $season = $item->season_id;
            return [$result, $oddOdd, $evenOdd, $matchday_id, $home, $away, $season];
          });
      })->flatten(1)->all();

      usort($matchResults, fn($a, $b) => $a[3] <=> $b[3]);
      return $matchResults;
    });
  }
  protected function getCountOddAndMatchday($total1, $total2)
  {
    // dd($total1);
    $count = 0;
    $oddOdd = [];
    $evenOdd = [];
    $matchday = [];
    for ($i = 0; $i < 6; $i++) {
      if ((collect($total1))->all()[$i][0] === (collect($total2))->all()[$i][0]) {
        $count++;
      }
      $result[] = (collect($total2))->all()[$i][0];
      $oddOdd[] = (collect($total2))->all()[$i][1];
      $evenOdd[] = (collect($total2))->all()[$i][2];
      $matchday[] = (collect($total2))->all()[$i][3];
    }
    // dd([$count, $oddOdd, $evenOdd, $matchday]);
    return [$count, $oddOdd, $evenOdd, $matchday, $result];
  }

  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'] - 4;

    for ($j = $start; $j < $end; $j++) {
      $seasonId = $this->getSeasonId($j + 1)->first();
      for ($l = 1; $l < 561; $l++) {
        $team = $this->arrangeTeam($l);

        $total1 = $this->processMatches([$team], $this->getSeasonId($j)->first(), [1, 30])->first();
        $total2 = $this->processMatches([$team], $this->getSeasonId($j + 1)->first(), [1, 30])->first();
        $total3 = $this->processMatches([$team], $this->getSeasonId($j + 2)->first(), [1, 30])->first();
        $total4 = $this->processMatches([$team], $this->getSeasonId($j + 3)->first(), [1, 30])->first();
        $total5 = $this->processMatches([$team], $this->getSeasonId($j + 4)->first(), [1, 30])->first();
        $team1 = $team['matches'][0][0];
        $team2 = $team['matches'][0][1];
        $team3 = $team['matches'][1][1];

        $seasonid = $this->getSeasonId($j)->first();

        $count = $this->getCountOddAndMatchday($total1, $total2)[0];

        // dd($total1);
        if ($count === 6) {
          $oddAndMatchday1 = $this->getCountOddAndMatchday($total2, $total3);
          $oddAndMatchday2 = $this->getCountOddAndMatchday($total2, $total4);
          $oddAndMatchday3 = $this->getCountOddAndMatchday($total2, $total5);
          $count1 = $oddAndMatchday1[0];
          $count2 = $oddAndMatchday2[0];
          $count3 = $oddAndMatchday3[0];
          $sameOdd1 = implode(",", $oddAndMatchday1[1]);
          $team_num = $l;
          $againtOdd1 = implode(",", $oddAndMatchday1[2]);
          $matchday1 = implode(",", $oddAndMatchday1[3]);
          $result = implode(",", $oddAndMatchday1[4]);
          Result::create([
            'num' => $j + 1,
            'season_id' => $seasonId,
            'result' => $result,
            'team_num' => $team_num,
            'team1' => "$team1 vs $team2",
            'team2' => "$team1 vs $team3",
            'team3' => "$team2 vs $team3",
            'type' => "same",
            'matchday' => $matchday1,
            'same_outcome' => $count1 === 6 ? 'Loss' : 'Win',
            'same_outcome2' => $count2 === 6 ? 'Loss' : 'Win',
            'same_outcome3' => $count3 === 6 ? 'Loss' : 'Win',
            'against_outcome' => $count1 === 0 ? 'Loss' : 'Win',
            'against_outcome2' => $count2 === 0 ? 'Loss' : 'Win',
            'against_outcome3' => $count3 === 0 ? 'Loss' : 'Win',
            'same_odd' => $sameOdd1,
            'against_odd' => $againtOdd1
          ]);
          // echo "season $seasonId, " . "team $team1 vs $team2, $team1 vs $team3, $team2 vs $team3 " . "type same" . " matchday $matchday1" . " same_outcome " . ($count1 === 6 ? '6 ==========> Loss' : '6 ==========> Win') . " againt_outcome " . ($count1 === 0 ? ', 0 ==========> Loss' : ', 0 ==========> Win') . "same_odd $sameOdd1 ". "against_odd $againtOdd1" . "\n";

        } elseif ($count === 0) {
          $oddAndMatchday1 = $this->getCountOddAndMatchday($total2, $total3);
          $oddAndMatchday2 = $this->getCountOddAndMatchday($total3, $total4);
          $oddAndMatchday3 = $this->getCountOddAndMatchday($total4, $total5);
          $count1 = $oddAndMatchday1[0];
          $count2 = $oddAndMatchday2[0];
          $count3 = $oddAndMatchday3[0];
          $sameOdd1 = implode(",", $oddAndMatchday1[1]);
          $againtOdd1 = implode(",", $oddAndMatchday1[2]);
          $matchday1 = implode(",", $oddAndMatchday1[3]);
          $result = implode(",", $oddAndMatchday1[4]);
          $team_num = $l;
          Result::create([
            'num' => $j + 1,
            'season_id' => $seasonId,
            'result' => $result,
            'team_num' => $team_num,
            'team1' => "$team1 vs $team2",
            'team2' => "$team1 vs $team3",
            'team3' => "$team2 vs $team3",
            'type' => "against",
            'matchday' => $matchday1,
            'same_outcome' => $count1 === 6 ? 'Loss' : 'Win',
            'same_outcome2' => $count2 === 6 ? 'Loss' : 'Win',
            'same_outcome3' => $count3 === 6 ? 'Loss' : 'Win',
            'against_outcome' => $count1 === 0 ? 'Loss' : 'Win',
            'against_outcome2' => $count2 === 0 ? 'Loss' : 'Win',
            'against_outcome3' => $count3 === 0 ? 'Loss' : 'Win',
            'same_odd' => $sameOdd1,
            'against_odd' => $againtOdd1
          ]);
        } else {
          echo "season $seasonid, " . "team $l " .  " $count" . "\n";
        }
        $count = 0;
      }
    }
  }
}

//team team1 team2 team3
//pick odd "1.60 1.5 "