<?php

namespace App\Jobs;

use App\Models\OverOrUnderMarket;
use App\Models\WinOrDrawMarket;
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
    public function __construct(public string $seasonId)
    {
        $this->main_data_url = env('MAIN_DATA_URL');
        $this->table_url = env('TABLE_URL');
    }
    /**
     * Execute the job.
     */
    public function handle()
    {
        for ($i = 26; $i <= 30; $i++) {
            $data = [];
            $apiUrls = [
                $this->main_data_url . ":" . $this->seasonId . "/" . $i,
                "$this->table_url/$this->seasonId/1/" . ($i - 1)
            ];

            foreach ($apiUrls as $apiUrl) {
                $response = Http::get($apiUrl);
                if ($response->failed()) {
                    throw new \Exception('Cannot fetch data from the api');
                }
                array_push($data, $response->json());
            }

            if ($this->firstWin === 'loss') {
                $existing = WinOrDrawMarket::where([
                    ['season_id', '=', $this->seasonId],
                    ['matchday_id', '=', $i],
                ])->first();
                $filterMatchdayDataService = new MatchdayDataClass($data, $i);
                $filteredWinOrDrawData = $filterMatchdayDataService->getWinOrDrawMatchday();
                $this->firstWin = $filteredWinOrDrawData['outcome'];

                if (!$existing) {
                    WinOrDrawMarket::create([...$filteredWinOrDrawData, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
                }
            }

            if ($this->secondWin === 'loss') {
                $existing = OverOrUnderMarket::where([
                    ['season_id', '=', $this->seasonId],
                    ['matchday_id', '=', $i],
                ])->first();
                $filterMatchdayDataService = new MatchdayDataClass($data, $i);
                $filteredOverOrUnder = $filterMatchdayDataService->getOverOrUnderMatchday();
                $this->secondWin = $filteredOverOrUnder['outcome'];
                if (!$existing) {
                    OverOrUnderMarket::create([...$filteredOverOrUnder, 'season_id' => $this->seasonId, 'matchday_id' => $i]);
                }
            }

            if ($this->firstWin === 'win' && $this->secondWin === 'win') {
                break;
            }
        }
    }
}
