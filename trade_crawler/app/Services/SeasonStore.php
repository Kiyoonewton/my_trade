<?php
namespace App\Services;
use MongoDB\Client;
use MongoDB\Collection;
class SeasonStore
{
    private Collection $matches;
    public function __construct()
    {
        $client = new Client(config('database.connections.mongodb.dsn'));
        $this->matches = $client->selectCollection(config('database.connections.mongodb.database'), 'win_or_draws');
        $this->matches->createIndex(['fixture_key'=>1], [
            'unique'=>true, 'partialFilterExpression'=>['fixture_key'=>['$type'=>'string']],
            'name'=>'unique_fixture_key',
        ]);
    }
    public function filter(string $season, string $competition): array
    {
        $ids = [$season];
        if (ctype_digit($season)) { $ids[] = (int) $season; }
        $filter = ['season_id'=>['$in'=>$ids]];
        if ($competition === 'virtual-football') {
            $filter['$or'] = [['competition_id'=>$competition], ['competition_id'=>null]];
        } else { $filter['competition_id'] = $competition; }
        return $filter;
    }
    public function rows(string $season, string $competition): array
    {
        return $this->matches->find($this->filter($season, $competition),
            ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']])->toArray();
    }
    public function key(array $row, string $season, string $competition): string
    {
        return hash('sha256', json_encode([$competition, $season,
            (int) $row['matchday_id'], $row['home'], $row['away']], JSON_THROW_ON_ERROR));
    }
    // Only exact duplicate fixture identities are collapsed. Other extra rows remain visible as incomplete data.
    public function reconcile(string $season, string $competition): int
    {
        $groups = []; $removed = 0;
        $rows = $this->rows($season, $competition);
        usort($rows, fn ($a,$b) => strcmp((string) $b['_id'], (string) $a['_id']));
        foreach ($rows as $row) {
            if (!isset($row['matchday_id'], $row['home'], $row['away'])) { continue; }
            $groups[$this->key($row, $season, $competition)][] = $row;
        }
        foreach ($groups as $key => $group) {
            $row = array_shift($group);
            foreach ($group as $duplicate) {
                $removed += $this->matches->deleteOne(['_id'=>$duplicate['_id']])->getDeletedCount();
            }
            $this->matches->updateOne(['_id'=>$row['_id']], ['$set'=>[
                'fixture_key'=>$key, 'season_id'=>$season, 'competition_id'=>$competition,
                'matchday_id'=>(int) $row['matchday_id'],
            ]]);
        }
        return $removed;
    }
    public function save(array $row, string $competition, ?int $sequence, string $source): void
    {
        $key = $this->key($row, $row['season_id'], $competition);
        $row += ['competition_id'=>$competition, 'source'=>$source, 'collected_at'=>date(DATE_ATOM)];
        if ($sequence !== null) { $row['season_sequence'] = $sequence; }
        $this->matches->updateOne(['fixture_key'=>$key], ['$set'=>$row, '$setOnInsert'=>['fixture_key'=>$key]], ['upsert'=>true]);
    }
}
