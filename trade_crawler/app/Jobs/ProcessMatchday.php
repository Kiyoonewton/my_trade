<?php

namespace App\Jobs;

use App\Models\OverOrUnderMarket;
use App\Models\TeamWinsAnalysis;
use App\Models\WinOrDrawMarket;
use App\Services\MatchdayDataClass;
use App\Services\WinAnalysisClass;
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
    public function handle()
    {
        for ($i = 1; $i <= 15; $i++) {
            // $data = [];
            // $apiUrls = [
            $data = $this->main_data_url . ":" . $this->seasonId . "/" . $i;
                // "$this->table_url/$this->seasonId/1/" . ($i - 1)
            // ];

            // foreach ($apiUrls as $apiUrl) {
            $response = Http::get($data);
            if ($response->failed()) {
                throw new \Exception('Cannot fetch data from the api');
            }
            $data = $response->json();
            // }

            return $data;

            // $existing = WinOrDrawMarket::where([
            //     ['season_id', '=', $this->seasonId],
            //     ['matchday_id', '=', $i],
            // ])->first();
            // $filterMatchdayDataService = new MatchdayDataClass($data, $i);
            // $filteredWinOrDrawData = $filterMatchdayDataService->getWinOrDrawMatchday();

            // if (!$existing) {
            //     WinOrDrawMarket::create([...$filteredWinOrDrawData, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
            // }

            // $existing = OverOrUnderMarket::where([
            //     ['season_id', '=', $this->seasonId],
            //     ['matchday_id', '=', $i],
            // ])->first();
            // $filterMatchdayDataService = new MatchdayDataClass($data, $i);
            // $filteredOverOrUnder = $filterMatchdayDataService->getOverOrUnderMatchday();

            // if (!$existing) {
            //     OverOrUnderMarket::create([...$filteredOverOrUnder, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
            // }

            // if ($i === 30) {
            //     $existing = TeamWinsAnalysis::where([
            //         ['season_id', '=', $this->seasonId],
            //         ['matchday_id', '=', $i],
            //     ])->first();
            //     $analysisService = new WinAnalysisClass($this->seasonId);
            //     $filteredAnalysis = $analysisService->initializeAnalysis();
            //     if (!$existing) {
            //         TeamWinsAnalysis::create([...$filteredAnalysis, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
            //     }
            // }
        }
    }
}
