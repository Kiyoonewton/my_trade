<?php
namespace App\Services;
use InvalidArgumentException;
use RuntimeException;
class MatchExport
{
    public const FIELDS = ['source', 'competition_id', 'season_id', 'season_sequence',
        'match_id', 'provider_match_id', 'provider_tournament_id', 'collected_at', 'matchday', 'kickoff', 'home_team_id', 'home_team',
        'away_team_id', 'away_team', 'odds_captured_at', 'home_odds', 'draw_odds',
        'away_odds', 'home_score', 'away_score', 'status', 'result'];

    public function row(array $record): array
    {
        $result = $record['result'] ?? null;
        $result = ['1'=>'H', 'x'=>'D', '2'=>'A', 'H'=>'H', 'D'=>'D', 'A'=>'A'][(string) $result] ?? null;
        $row = array_intersect_key($record, array_flip(self::FIELDS));
        $row['competition_id'] = $record['competition_id'] ?? 'virtual-football';
        $row['match_id'] = (string) ($record['_id'] ?? $record['match_id'] ?? '');
        $row['matchday'] = $record['matchday_id'] ?? $record['matchday'] ?? null;
        foreach (['home', 'away'] as $side) {
            $row[$side.'_team'] = $record[$side] ?? $record[$side.'_team'] ?? null;
        }
        foreach (['home', 'draw', 'away'] as $side) {
            $row[$side.'_odds'] = $record[$side.'_odd'] ?? $record[$side.'_odds'] ?? null;
        }
        $row['result'] = $result;
        $row['status'] = $record['status'] ?? ($result ? 'settled' : 'unknown');
        return array_replace(array_fill_keys(self::FIELDS, null), $row);
    }

    public function write(iterable $records, string $path, string $format): int
    {
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new InvalidArgumentException('Format must be csv or json.');
        }
        if (file_exists($path)) {
            throw new RuntimeException('Output already exists; choose a new filename.');
        }
        $temporary = tempnam(dirname($path), '.export-');
        if ($temporary === false) { throw new RuntimeException('Cannot create output.'); }
        $file = fopen($temporary, 'wb');
        if ($file === false) { unlink($temporary); throw new RuntimeException('Cannot open output.'); }
        $count = 0;
        try {
            if ($format === 'csv') { fputcsv($file, self::FIELDS, ',', '"', ''); }
            else { fwrite($file, "[\n"); }
            foreach ($records as $record) {
                $row = $this->row(is_array($record) ? $record : $record->getAttributes());
                if ($format === 'csv') { fputcsv($file, $row, ',', '"', ''); }
                else {
                    fwrite($file, ($count ? ",\n" : '').json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                }
                $count++;
            }
            if ($format === 'json') { fwrite($file, "\n]\n"); }
            if (!fflush($file)) { throw new RuntimeException('Could not flush export.'); }
            fclose($file); $file = null;
            // tempnam creates mode 0600; exports must also be readable on the host.
            if (!chmod($temporary, 0644)) { throw new RuntimeException('Cannot set export permissions.'); }
            // Hard-link publishes atomically without overwriting an existing destination.
            if (!link($temporary, $path)) { throw new RuntimeException('Cannot publish output.'); }
            return $count;
        } finally {
            if (is_resource($file)) { fclose($file); }
            unlink($temporary);
        }
    }
}
