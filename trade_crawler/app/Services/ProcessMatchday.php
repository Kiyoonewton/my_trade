<?php

namespace App\Services;

use App\Models\OddOrEven;
use App\Models\OddOrEven1;
use App\Models\OddOrEven2;
use App\Models\Season;
use App\Models\Season1;
use App\Models\Season2;
use App\Services\MatchdayDataClass;
use Illuminate\Support\Facades\Http;

enum Model_Type: string
{
  case VFL = 'VFL';
  case VFB = 'VFB';
  case VFE = 'VFE';
}

class ProcessMatchday
{

  /**
   * Create a new job instance.
   */
  protected string $main_data_url = "";
  protected string $table_url = "";

  public function __construct(public string $seasonId, public string $model_type)
  {
    $this->main_data_url = env('MAIN_DATA_URL', 'https://vgls.betradar.com/vfl/feeds/?/bet9ja/en/Europe:Berlin/gismo/vfc_stats_round_odds2/vf:season');
  }
  /**
   * Execute the job.
   */
  protected function fetchData(int $i)
  {
    $data = $this->main_data_url . ":" . $this->seasonId . "/" . $i;

    $response = Http::get($data);
    if ($response->failed()) {
      throw new \Exception('Cannot fetch data from the api');
    }
    $data = $response->json();
    return $data;
  }

  public function handle()
  {
    switch ($this->model_type) {
      case 'VFL':
        $match_type_model = OddOrEven1::class;
        $season_model = Season1::class;
        break;
      case 'VFB':
        $match_type_model = OddOrEven2::class;
        $season_model = Season2::class;
        break;
      default:
        $match_type_model = OddOrEven::class;
        $season_model = Season::class;
        break;
    }

    $results = collect();
    for ($i = 1; $i <= 30; $i++) {

      $existingCount = $match_type_model::where([
        ['season_id', '=', $this->seasonId],
        ['matchday_id', '=', $i],
      ])->count();

      if ($existingCount === 8) {
        $results = $results->merge(array_fill(0, 8, 'complete'));
        continue;
      } else {
        $match_type_model::where([
          ['season_id', '=', $this->seasonId],
          ['matchday_id', '=', $i],
        ])->delete();
      }

      $filterMatchdayDataService = new MatchdayDataClass($this->fetchData($i));
      $filteredWinOrDrawDatas = $filterMatchdayDataService->getOddOrEvenMatchday();

      $createdEntries = collect($filteredWinOrDrawDatas)->map(function ($filteredWinOrDrawData) use ($match_type_model) {
        $created = $match_type_model::create([
          ...$filteredWinOrDrawData,
          'season_id' => $this->seasonId
        ]);

        return [
          ...$filteredWinOrDrawData,
          'season_id' => $this->seasonId,
          'created_id' => $created->id
        ];
      });
      $results = $results->merge($createdEntries);
    }

    $existingSeason = $season_model::where('seasonId', $this->seasonId)->first();
    if (!$existingSeason) {
      $season_model::create([
        'seasonId' => $this->seasonId
      ]);
    }
    return $results->all();
  }
}
