<?php

namespace App\Jobs;

use App\Models\Season;
use App\Models\WinOrDraw;
use App\Services\MatchdayDataClass;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessWinOrDraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $main_data_url = "";

    public function __construct(public string $seasonId)
    {
        $this->main_data_url = env('MAIN_DATA_URL', 'https://vgls.betradar.com/vfl/feeds/?/bet9ja/en/Europe:Berlin/gismo/vfc_stats_round_odds2/vf:season');
    }

    protected function fetchData(int $i)
    {
        $url = $this->main_data_url . ":" . $this->seasonId . "/" . $i;

        // Retry up to 3 times with exponential backoff
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::timeout(60)->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                $attempt++;
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt)); // 2, 4, 8 seconds
                }
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    throw new \Exception("Failed to fetch data after {$maxRetries} attempts: " . $e->getMessage());
                }
                sleep(pow(2, $attempt));
            }
        }

        throw new \Exception('Cannot fetch data from the api after ' . $maxRetries . ' attempts');
    }

    public function handle()
    {
        $results = collect();

        for ($i = 1; $i <= 30; $i++) {
            $existingCount = WinOrDraw::where([
                ['season_id', '=', $this->seasonId],
                ['matchday_id', '=', $i],
            ])->count();

            if ($existingCount === 8) {
                $results = $results->merge(array_fill(0, 8, 'complete'));
                continue;
            } else {
                WinOrDraw::where([
                    ['season_id', '=', $this->seasonId],
                    ['matchday_id', '=', $i],
                ])->delete();
            }

            $service = new MatchdayDataClass($this->fetchData($i));
            $matchdayData = $service->getWinOrDrawMatchday();

            $createdEntries = collect($matchdayData)->map(function ($data) {
                $created = WinOrDraw::create([
                    ...$data,
                    'season_id' => $this->seasonId
                ]);

                return [
                    ...$data,
                    'season_id' => $this->seasonId,
                    'created_id' => $created->id
                ];
            });

            $results = $results->merge($createdEntries);
        }

        $existingSeason = Season::where('seasonId', $this->seasonId)->first();
        if (!$existingSeason) {
            Season::create(['seasonId' => $this->seasonId]);
        }

        return $results->all();
    }
}
