<?php
namespace App\Console\Commands;
use App\Services\MatchNormalizer;
use App\Services\SeasonIntegrity;
use App\Services\SeasonStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
class CollectSeason extends Command
{
    protected $signature = 'matches:collect {season} {--competition=virtual-football} {--matchdays=30} {--matches-per-matchday=8} {--sequence=}';
    protected $description = 'Skip complete seasons and repair missing matchdays (default: 240 matches)';
    public function handle(MatchNormalizer $normalizer, SeasonIntegrity $integrity): int
    {
        $days = filter_var($this->option('matchdays'), FILTER_VALIDATE_INT);
        $expected = filter_var($this->option('matches-per-matchday'), FILTER_VALIDATE_INT);
        $base = config('services.match_feed.url');
        $season = $this->argument('season');
        $competition = $this->option('competition');
        if (!$days || $days < 1 || !$expected || $expected < 1 || !$base
            || !preg_match('/^[A-Za-z0-9_-]+$/D', $season) || !$competition) {
            $this->error('Set MATCH_FEED_URL, a safe season ID and positive season dimensions.');
            return self::FAILURE;
        }
        $lockDir = env('COLLECTION_LOCK_DIR', '/state');
        $lock = @fopen($lockDir.'/collection.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->error('Another collector is active, or the collection lock directory is not writable.');
            if (is_resource($lock)) { fclose($lock); }
            return self::FAILURE;
        }
        try {
            $store = new SeasonStore;
            $removed = $store->reconcile($season, $competition);
            if ($removed) { $this->warn("Removed {$removed} duplicate fixtures in season {$season}."); }
            $rows = $store->rows($season, $competition);
            if ($integrity->completeSeason($rows, $days, $expected)) {
                $this->info("SKIP {$season}: already complete (".count($rows).' unique settled matches).');
                return self::SUCCESS;
            }
            for ($day = 1; $day <= $days; $day++) {
                $stored = array_values(array_filter($rows, fn ($r) => (int) ($r['matchday_id'] ?? 0) === $day));
                if ($integrity->completeDay($stored, $expected)) { continue; }
                try {
                    $response = Http::timeout(30)->retry(3, 500)->get($base.':'.$season.'/'.$day)->throw();
                } catch (ConnectionException $e) {
                    $this->warn("NETWORK {$season}, matchday {$day}: ".$e->getMessage()); return 2;
                } catch (RequestException $e) {
                    $code = $e->response->status();
                    if ($code === 408 || $code === 429 || $code >= 500) {
                        $this->warn("NETWORK {$season}, matchday {$day}: HTTP {$code}; not an unavailable season."); return 2;
                    }
                    $this->warn("INCOMPLETE {$season}, matchday {$day}: HTTP {$code}; moving on."); return 3;
                }
                $dir = env('RAW_DATA_DIR', '/raw');
                if (!is_dir($dir) || !is_writable($dir)) { throw new \RuntimeException('Raw archive directory must be writable.'); }
                $saved = file_put_contents($dir.'/'.$season.'-'.$day.'-'.bin2hex(random_bytes(6)).'.json', $response->body(), LOCK_EX);
                if ($saved === false) { throw new \RuntimeException('Cannot archive response.'); }
                try {
                    $data = $response->json();
                    if (!is_array($data)) { throw new \InvalidArgumentException('API response is not JSON match data.'); }
                    $incoming = $normalizer->normalize($data, $season, $day);
                    if (!$integrity->completeDay($incoming, $expected)) {
                        throw new \InvalidArgumentException('API did not supply the expected unique settled matches.');
                    }
                } catch (\InvalidArgumentException $e) {
                    $this->warn("INCOMPLETE {$season}, matchday {$day}: ".$e->getMessage()); return 3;
                }
                foreach ($incoming as $row) {
                    $store->save($row, $competition,
                        $this->option('sequence') !== null ? (int) $this->option('sequence') : null, $base);
                }
                $this->info("{$season}: repaired matchday {$day}.");
            }
            $final = $store->rows($season, $competition);
            if (!$integrity->completeSeason($final, $days, $expected)) {
                $this->warn("INCOMPLETE {$season}: ".count($final).'/'.($days*$expected).' stored; moving on.');
                return 3;
            }
            $this->info("COMPLETE {$season}: ".count($final).' unique settled matches.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("ERROR {$season}: ".$e->getMessage()); return self::FAILURE;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
