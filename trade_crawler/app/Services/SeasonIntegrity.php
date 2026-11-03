<?php
namespace App\Services;
class SeasonIntegrity
{
    public function completeDay(array $rows, int $expected): bool
    {
        if (count($rows) !== $expected) { return false; }
        $teams = [];
        foreach ($rows as $row) {
            if (!in_array($row['result'] ?? null, ['H', 'D', 'A', '1', 'x', '2'], true)) { return false; }
            foreach (['home', 'away'] as $side) {
                if (empty($row[$side]) || isset($teams[$row[$side]])) { return false; }
                $teams[$row[$side]] = true;
            }
            foreach (['home_odd', 'draw_odd', 'away_odd'] as $field) {
                if (!is_numeric($row[$field] ?? null) || $row[$field] <= 1) { return false; }
            }
        }
        return true;
    }
    public function completeSeason(array $rows, int $days, int $matches): bool
    {
        if (count($rows) !== $days * $matches) { return false; }
        for ($day = 1; $day <= $days; $day++) {
            $group = array_values(array_filter($rows, fn ($r) => (int) ($r['matchday_id'] ?? 0) === $day));
            if (!$this->completeDay($group, $matches)) { return false; }
        }
        return true;
    }
}
