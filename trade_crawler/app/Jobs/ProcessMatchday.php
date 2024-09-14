<?php

namespace App\Jobs;

use App\Models\OverOrUnder;
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
    public function __construct(public string $seasonId, public string $team1, public string $team2)
    {
        $this->main_data_url = env('MAIN_DATA_URL', 'https://vgls.betradar.com/vfl/feeds/?/bet9ja/en/Europe:Berlin/gismo/vfc_stats_round_odds2/vf:season');
    }
    /**
     * Execute the job.
     */

    protected function filterByFeatures(mixed $data, string $team1, string $team2)
    {
        return (collect(collect($data['doc'])->first()['data']['odds'])->filter(function ($odd) use ($team1, $team2) {
            return (
                ($odd["teams"]["home"]["name"] === $team1 && $odd["teams"]["away"]["name"] === $team2) ||
                ($odd["teams"]["home"]["name"] === $team2 && $odd["teams"]["away"]["name"] === $team1)
            );
        }))->first();
    }
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

    protected function fetchFilteredByFeatures(int $index)
    {
        return $this->filterByFeatures($this->fetchData($index), $this->team1, $this->team2);
    }

    public function handle()
    {
        for ($i = 1; $i <= 15; $i++) {

            $existing = OverOrUnder::where([
                ['season_id', '=', $this->seasonId],
                ['matchday_id', '=', $i],
            ])->first();

            if ($existing) {
                return null;
            }

            $filteredFeature = $this->fetchFilteredByFeatures($i);
            if ($filteredFeature) {
                return collect([$i, $i + 15])->map(function ($index) {
                    $filterMatchdayDataService = new MatchdayDataClass($this->fetchData($index)['queryUrl'], $this->fetchFilteredByFeatures($index));
                    $filteredWinOrDrawData = $filterMatchdayDataService->getOverOrUnderMatchday();
                    OverOrUnder::create([
                        ...$filteredWinOrDrawData,
                        'season_id' => $this->seasonId
                    ]);
                    return [
                        ...$filteredWinOrDrawData,
                        'season_id' => $this->seasonId
                    ];
                });
            }
        }
    }
}
