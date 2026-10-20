<?php
namespace App\Console\Commands;
use App\Models\MatchRecord;
use App\Services\MatchNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
class CollectSeason extends Command
{
    protected $signature = 'matches:collect {season} {--competition=virtual-football} {--matchdays=38} {--matches-per-day=10} {--sequence=}';
    protected $description = 'Collect 1X2 odds and results for an explicit season ID';
    public function handle(MatchNormalizer $normalizer): int
    {
        $days = filter_var($this->option('matchdays'), FILTER_VALIDATE_INT);
        $expected = filter_var($this->option('matches-per-day'), FILTER_VALIDATE_INT);
        $base = config('services.match_feed.url');
        $season = $this->argument('season');
        if (!$days || $days < 1 || !$expected || $expected < 1 || !$base
            || !preg_match('/^[A-Za-z0-9_-]+$/D', $season)) {
            $this->error('Set MATCH_FEED_URL, a safe season ID and positive season dimensions.');
            return self::FAILURE;
        }
        try {
            for ($day = 1; $day <= $days; $day++) {
                $response = Http::timeout(60)->retry(3, 500)->get($base.':'.$season.'/'.$day)->throw();
                $data = $response->json();
                $dir = '/raw';
                if (!is_dir($dir) || !is_writable($dir)) { throw new \RuntimeException('/raw must be writable.'); }
                file_put_contents($dir.'/'.$season.'-'.$day.'-'.bin2hex(random_bytes(6)).'.json',
                    $response->body(), LOCK_EX) !== false || throw new \RuntimeException('Cannot archive response.');
                $rows = $normalizer->normalize($data, $season, $day);
                $keys = array_map(fn ($r) => $r['home']."\0".$r['away'], $rows);
                if (count($rows) !== $expected || count(array_unique($keys)) !== $expected) {
                    throw new \RuntimeException("Unexpected or duplicate matches on matchday {$day}; nothing written for this day.");
                }
                foreach ($rows as $row) {
                    $identity = ['season_id'=>$season, 'matchday_id'=>$day,
                        'home'=>$row['home'], 'away'=>$row['away']];
                    // Legacy rows have no competition field; reuse them rather than duplicating.
                    $existing = MatchRecord::where($identity)->where(function ($q) {
                        $q->where('competition_id', $this->option('competition'))->orWhereNull('competition_id');
                    })->first();
                    $record = $existing ?? new MatchRecord;
                    $record->fill($row + [
                        'competition_id'=>$this->option('competition'),
                        'source'=>$base, 'collected_at'=>now()->toIso8601String(),
                        'season_sequence'=>$this->option('sequence') !== null ? (int) $this->option('sequence') : null,
                    ])->save();
                }
                $this->info("Stored matchday {$day}: ".count($rows).' matches.');
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
    }
}
