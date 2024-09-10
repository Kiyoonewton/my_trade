<?php

namespace App\Jobs;

use App\Models\TeamWinsAnalysis;
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
    public string $firstWin = 'loss';
    public string $secondWin = 'loss';
    public function __construct(public string $seasonId, public string $team1, public string $team2)
    {
        $this->main_data_url = env('MAIN_DATA_URL');
        $this->table_url = env('TABLE_URL');
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

    public function handle()
    {
        for ($i = 1; $i <= 15; $i++) {

            $existing = TeamWinsAnalysis::where([
                ['season_id', '=', $this->seasonId],
                ['matchday_id', '=', $i],
            ])->first();

            if ($existing) {
                return;
            }

            $filteredFeature = $this->filterByFeatures($this->fetchData($i), $this->team1, $this->team2);
            if ($filteredFeature) {
                $filterMatchdayDataService = new MatchdayDataClass($this->fetchData($i)['queryUrl'], $filteredFeature);
                $filteredWinOrDrawData = $filterMatchdayDataService->getOverOrUnderMatchday();
                TeamWinsAnalysis::create([...$filteredWinOrDrawData, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
                //plus 15
                $filterMatchdayDataService = new MatchdayDataClass($this->fetchData($i + 15)['queryUrl'], $filteredFeature);
                $filteredWinOrDrawData = $filterMatchdayDataService->getOverOrUnderMatchday();
                TeamWinsAnalysis::create([...$filteredWinOrDrawData, 'season_id' => $this->seasonId, 'matchday_id' => $i + 15]);
                break;
            }
        }
    }
}
