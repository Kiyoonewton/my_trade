<?php

namespace App\Jobs;

use App\Models\OverOrUnder;
use App\Models\Season;
use App\Services\MatchdayDataClass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessMatchday implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    protected string $main_data_url = "";
    protected string $table_url = "";
    public function __construct(public string $seasonId)
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
        $results = collect();
        for ($i = 1; $i <= 30; $i++) {

            $existingCount = OverOrUnder::where([
                ['season_id', '=', $this->seasonId],
                ['matchday_id', '=', $i],
            ])->count();

            if ($existingCount === 8) {
                $results = $results->merge(array_fill(0, 8, 'complete'));
                continue;
            } else {
                OverOrUnder::where([
                    ['season_id', '=', $this->seasonId],
                    ['matchday_id', '=', $i],
                ])->delete();
            }

            $filterMatchdayDataService = new MatchdayDataClass($this->fetchData($i));
            $filteredWinOrDrawDatas = $filterMatchdayDataService->getOverOrUnderMatchday();
            $createdEntries = collect($filteredWinOrDrawDatas)->map(function ($filteredWinOrDrawData) {
                $created = OverOrUnder::create([
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

        $existingSeason = Season::where('seasonId', $this->seasonId)->first();
        if (!$existingSeason) {
            Season::create([
                'seasonId' => $this->seasonId
            ]);
        }
        return $results->all();
    }
}
