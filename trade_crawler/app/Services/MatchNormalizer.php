<?php
namespace App\Services;
use InvalidArgumentException;
class MatchNormalizer
{
    public function normalize(array $data, string $season, int $matchday, ?string $tournament = null): array
    {
        $events = $data['doc'][0]['data']['odds'] ?? null;
        if (!is_array($events) || !$events) {
            throw new InvalidArgumentException('Response has no match odds.');
        }
        $rows = [];
        foreach ($events as $event) {
            if ($tournament !== null && ($event['tournament_id'] ?? null) !== $tournament) {
                throw new \RuntimeException('Feed tournament does not match the selected competition. Check the league and season ID.');
            }
            $markets = array_values(array_filter($event['market'] ?? [],
                fn ($market) => (string) $market['id'] === '1'));
            if (count($markets) !== 1) {
                throw new InvalidArgumentException('Expected one 1X2 market per match.');
            }
            $outcomes = [];
            foreach ($markets[0]['outcome'] as $outcome) {
                $outcomes[(string) $outcome['id']] = $outcome;
            }
            // Provider IDs: 1 = home, 2 = draw, 3 = away (existing feed convention).
            foreach (['1', '2', '3'] as $id) {
                if (!isset($outcomes[$id]) || !is_numeric($outcomes[$id]['odds'] ?? null)
                    || $outcomes[$id]['odds'] <= 1) {
                    throw new InvalidArgumentException('Missing or invalid 1X2 odds.');
                }
            }
            $winners = array_keys(array_filter($outcomes,
                fn ($outcome) => (string) ($outcome['result'] ?? '') === '1'));
            if (count($winners) > 1) {
                throw new InvalidArgumentException('Multiple winning outcomes.');
            }
            $home = $event['teams']['home']['name'] ?? '';
            $away = $event['teams']['away']['name'] ?? '';
            if (!$home || !$away || $home === $away) {
                throw new InvalidArgumentException('Invalid teams.');
            }
            $result = $winners ? ['1'=>'H', '2'=>'D', '3'=>'A'][(string) $winners[0]] : null;
            $rows[] = [
                'season_id' => $season, 'matchday_id' => $matchday,
                'provider_match_id' => $event['id'] ?? null,
                'provider_tournament_id' => $event['tournament_id'] ?? null,
                'home_team_id' => $event['teams']['home']['id'] ?? null,
                'away_team_id' => $event['teams']['away']['id'] ?? null,
                'home' => $home, 'away' => $away,
                'home_odd' => (float) $outcomes['1']['odds'],
                'draw_odd' => (float) $outcomes['2']['odds'],
                'away_odd' => (float) $outcomes['3']['odds'],
                'result' => $result, 'status' => $result ? 'settled' : 'unknown',
            ];
        }
        return $rows;
    }
}
