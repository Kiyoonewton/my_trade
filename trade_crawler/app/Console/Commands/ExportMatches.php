<?php
namespace App\Console\Commands;
use App\Models\MatchRecord;
use App\Services\MatchExport;
use Illuminate\Console\Command;
class ExportMatches extends Command
{
    protected $signature = 'matches:export {output : CSV or JSON destination} {--format=csv} {--season=} {--competition=}';
    protected $description = 'Stream stored matches to CSV or a JSON array';
    public function handle(MatchExport $export): int
    {
        $query = MatchRecord::query();
        if ($this->option('season') !== null) { $query->where('season_id', $this->option('season')); }
        if ($this->option('competition') === 'virtual-football') {
            $query->where(fn ($q) => $q->where('competition_id', 'virtual-football')->orWhereNull('competition_id'));
        } elseif ($this->option('competition') !== null) {
            $query->where('competition_id', $this->option('competition'));
        }
        try {
            $count = $export->write($query->orderBy('competition_id')->orderBy('season_id')->orderBy('matchday_id')->orderBy('_id')->cursor(),
                $this->argument('output'), $this->option('format'));
            $this->info("Exported {$count} matches.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }
    }
}
