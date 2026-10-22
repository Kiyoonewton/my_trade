<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use MongoDB\Client;
use App\Services\SeasonIntegrity;
function verify(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$db = (new Client(config('database.connections.mongodb.dsn')))->selectDatabase(config('database.connections.mongodb.database'));
$matches = $db->selectCollection('win_or_draws');
$season = 'test-'.bin2hex(random_bytes(5));
$competition = 'test-collection';
$requests = [];
$payload = function (int $count = 8, bool $settled = true): array {
    $events = [];
    for ($i=0; $i<$count; $i++) {
        $events[] = ['teams'=>['home'=>['name'=>'Home '.$i], 'away'=>['name'=>'Away '.$i]],
            'market'=>[['id'=>1, 'outcome'=>[
                ['id'=>'1','odds'=>2.1,'result'=>$settled ? 1 : 0],
                ['id'=>'2','odds'=>3.2,'result'=>0], ['id'=>'3','odds'=>3.8,'result'=>0],
            ]]]];
    }
    return ['doc'=>[['data'=>['odds'=>$events]]]];
};
$run = function () use ($season,$competition) {
    return Artisan::call('matches:collect', ['season'=>$season, '--competition'=>$competition, '--matchdays'=>30, '--matches-per-matchday'=>8]);
};
$fake = function (int $count = 8, bool $settled = true) use (&$requests,$payload) {
    Http::swap(new Illuminate\Http\Client\Factory);
    Http::fake(function ($request) use (&$requests,$payload,$count,$settled) {
        $requests[] = $request->url(); return Http::response($payload($count,$settled));
    });
};
try {
    $fake();
    verify($run() === 0, 'Initial season must complete: '.Artisan::output());
    verify($matches->countDocuments(['season_id'=>$season]) === 240, 'Exactly 240 fixtures');
    verify(count($requests) === 30, 'Fetch 30 matchdays');
    $requests = []; $fake();
    verify($run() === 0 && count($requests) === 0, 'Complete season skipped without requests');
    $matches->deleteOne(['season_id'=>$season,'matchday_id'=>7]);
    $requests = []; $fake(7);
    verify($run() === 3, 'Incomplete API response must report incomplete');
    verify(str_contains(Artisan::output(),'INCOMPLETE'), 'Terminal reports incomplete');
    verify($matches->countDocuments(['season_id'=>$season]) === 239, 'Preserve progress after incomplete API');
    verify(count($requests) === 1, 'Only missing matchday fetched');
    $requests = []; $fake(8,false);
    verify($run() === 3 && $matches->countDocuments(['season_id'=>$season]) === 239, 'Unsettled response not counted complete');
    foreach ([429, 503, 404] as $status) {
        Http::swap(new Illuminate\Http\Client\Factory);
        Http::fake(fn () => Http::response([], $status));
        verify($run() === ($status === 404 ? 3 : 2), 'HTTP failure classification');
    }
    $requests = []; $fake();
    verify($run() === 0, 'Retry repairs incomplete season');
    verify($matches->countDocuments(['season_id'=>$season]) === 240 && count($requests) === 1, 'Repair without duplicate matches');
    $duplicate = $matches->findOne(['season_id'=>$season], ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']]);
    unset($duplicate['_id'],$duplicate['fixture_key']);
    $matches->insertOne($duplicate);
    $requests = []; $fake();
    verify($run() === 0 && $matches->countDocuments(['season_id'=>$season]) === 240, 'Legacy exact duplicate removed');
    verify(count($requests) === 0, 'Duplicate repair does not refetch complete season');
    $all = $matches->find(['season_id'=>$season], ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']])->toArray();
    verify((new SeasonIntegrity)->completeSeason($all,30,8), 'Full integrity check');
    $legacy = $matches->findOne(['season_id'=>$season], ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']]);
    unset($legacy['_id'], $legacy['fixture_key'], $legacy['competition_id']);
    $legacy['home'] = 'Legacy English';
    $legacy['away'] = 'Legacy Away';
    $insert = $matches->insertOne($legacy);
    $store = new App\Services\SeasonStore;
    verify(count($store->rows($season, 'virtual-bundesliga')) === 0, 'German lookup must not adopt untagged English rows');
    verify(count($store->rows($season, 'virtual-euro')) === 0, 'Euro lookup must not adopt untagged English rows');
    verify(count($store->rows($season, 'virtual-football')) === 1, 'English lookup retains legacy rows');
    $store->reconcile($season, 'virtual-bundesliga');
    verify(!isset($matches->findOne(['_id'=>$insert->getInsertedId()])['competition_id']), 'No cross-league legacy mutation');
    $matches->deleteOne(['_id'=>$insert->getInsertedId()]);
    $row = ['season_id'=>$season,'matchday_id'=>1,'home'=>'Shared Home','away'=>'Shared Away',
        'home_odd'=>2.1,'draw_odd'=>3.2,'away_odd'=>3.8,'result'=>'H','status'=>'settled'];
    $store->save($row, 'virtual-bundesliga', null, 'test');
    $store->save($row, 'virtual-euro', null, 'test');
    verify(count($store->rows($season, 'virtual-bundesliga')) === 1, 'German fixture stored separately');
    verify(count($store->rows($season, 'virtual-euro')) === 1, 'Euro fixture stored separately');
    verify(!empty($store->rows($season, 'virtual-euro')[0]['collected_at']), 'New records have collection time');
    $target = sys_get_temp_dir().'/trade-export-'.bin2hex(random_bytes(5)).'.json';
    try {
        verify(Artisan::call('matches:export', ['output'=>$target, '--format'=>'json', '--season'=>$season,
            '--competition'=>'virtual-euro']) === 0, 'League export succeeds');
        $exported = json_decode(file_get_contents($target), true);
        verify(count($exported) === 1 && $exported[0]['competition_id'] === 'virtual-euro', 'Export isolates selected competition');
        verify(!empty($exported[0]['collected_at']), 'Export preserves collection time');
    } finally { if (file_exists($target)) { unlink($target); } }
    $requests = [];
    $fake();
    verify(Artisan::call('matches:collect', ['season'=>$season, '--competition'=>'virtual-bundesliga']) === 1,
        'Wrong feed tournament is a fatal league mismatch');
    verify(str_contains(Artisan::output(), 'tournament'), 'Mismatch is explained');
    verify(count($store->rows($season, 'virtual-bundesliga')) === 1, 'Mismatch did not write API rows');
    echo "Season collection database checks passed.\n";
} finally { $matches->deleteMany(['season_id'=>$season]); }
