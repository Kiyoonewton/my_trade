<?php
namespace App\Console\Commands;
use App\Services\SeasonStore;
use Illuminate\Console\Command;

class CompletedSeasons extends Command
{
    protected $signature = 'matches:completed {--competition=virtual-football} {--matchdays=} {--matches-per-matchday=}';
    protected $description = 'Print JSON IDs of fully validated stored seasons without changing MongoDB';

    public function handle(): int
    {
        $competition = $this->option('competition');
        $profile = config('competitions.'.$competition, []);
        $days = filter_var($this->option('matchdays') ?? ($profile['matchdays'] ?? null), FILTER_VALIDATE_INT);
        $matches = filter_var($this->option('matches-per-matchday') ?? ($profile['matches_per_matchday'] ?? null), FILTER_VALIDATE_INT);
        if (!$days || $days < 1 || !$matches || $matches < 1 || !is_string($competition)
            || !preg_match('/^[A-Za-z0-9_-]+$/D', $competition)) {
            $this->error('Invalid competition or season format.');
            return self::FAILURE;
        }
        try {
            $ids = (new SeasonStore(false))->completedSeasons($competition, $days, $matches);
            $this->line(json_encode(['competition'=>$competition, 'season_ids'=>$ids], JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
    }
}
