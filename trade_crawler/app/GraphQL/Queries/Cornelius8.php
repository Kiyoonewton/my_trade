<?php


namespace App\GraphQL\Queries;

use App\Models\OverOrUnder;
use App\Models\Result;
use App\Models\TeamArranger;
use App\Trait\TeamArrangerTrait as TraitTeamArrangerTrait;
use Illuminate\Support\Facades\DB;

use function Illuminate\Log\log;
use function PHPUnit\Framework\matches;

class Cornelius8
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
            $sameOdd = $result === 1 ? $item->over : $item->under;
            $againstOdd = $result === 1 ? $item->under : $item->over;
            return [$result, $sameOdd, $againstOdd, $matchday_id];
          });
      })->flatten(1)->all();
      return [
        'matches' => $matchResults,
        'count' => collect($matchResults)->count()
      ];
    });
  }
  protected function findArraysWithAllElementsInCommon($arrays)
  {
    $hashMap = [];
    $count = count($arrays);

    // Loop through each array
    for ($i = 0; $i < $count; $i++) {
      // Sort the array to ensure order-independent comparison
      $sortedArray = $arrays[$i]['matchIds'];
      sort($sortedArray);

      // Convert the sorted array to a string to use as a hash key
      $hashKey = implode(',', $sortedArray);

      // If the hash key exists, append the current index to its list
      if (isset($hashMap[$hashKey])) {
        $hashMap[$hashKey][] = $i;
      } else {
        // Create a new entry in the hashMap for this unique sorted array
        $hashMap[$hashKey] = [$i];
      }
    }
    $result = [];
    foreach ($hashMap as $key => $indexes) {
      $result1 = [];
      if (count($indexes) > 1) {

        foreach ($indexes as $index) {
          usort($arrays[$index]['matches'], function ($a, $b) {
            return $a[3] <=> $b[3];
          });
          $result1[] = array_map(function ($item) {
            return array_map(function ($data) {
              return $data[0];
            }, $item);
          }, [$arrays[$index]['matches']])[0];
        }
      }
      if (!empty($result1[0])) {
        $result[] = $result1;
      }
    }
    return $result;
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
    }
    return [$count, $sameOdd, $againstOdd, $matchday];
  }

  protected function extractAndSortThirdColumn($array)
  {
    $thirdColumn = array_map(function ($item) {
      return $item[3];
    }, $array['matches']);
    sort($thirdColumn);

    return $thirdColumn;
  }
  public function __invoke($_, $args)
  {
    $start = $args['start'];
    $end = $args['end'];

    for ($j = $start; $j < $end; $j++) {
      $seasonId = $this->getSeasonId($j + 1)->first();
      $arrray_of_matchDays = [];
      for ($l = 1; $l < 561; $l++) {
        $team = $this->arrangeTeam($l);

        $total1 = $this->processMatches([$team], $seasonId, [1, 30])->first();
        $arrray_of_matchDays[] = [...$total1, 'matchIds' => $this->extractAndSortThirdColumn($total1)];
        // $arrray_of_matchDays[] = $this->extractAndSortThirdColumn($total1);
      }
      return $this->findArraysWithAllElementsInCommon($arrray_of_matchDays);
    }
  }
}


//team team1 team2 team3
//pick odd "1.60 1.5 "