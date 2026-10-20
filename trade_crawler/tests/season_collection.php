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
    return Artisan::call('matches:collect', ['season'=>$season, '--competition'=>$competition]);
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
    verify($run() === 1, 'Incomplete API response must report incomplete');
    verify(str_contains(Artisan::output(),'INCOMPLETE'), 'Terminal reports incomplete');
    verify($matches->countDocuments(['season_id'=>$season]) === 239, 'Preserve progress after incomplete API');
    verify(count($requests) === 1, 'Only missing matchday fetched');
    $requests = []; $fake(8,false);
    verify($run() === 1 && $matches->countDocuments(['season_id'=>$season]) === 239, 'Unsettled response not counted complete');
    $requests = []; $fake();
    verify($run() === 0, 'Retry repairs incomplete season');
    verify($matches->countDocuments(['season_id'=>$season]) === 240 && count($requests) === 1, 'Repair without duplicate matches');
    $duplicate = $matches->findOne(['season_id'=>$season], ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']]);
    unset($duplicate['_id'],$duplicate['fixture_key'],$duplicate['competition_id']);
    $matches->insertOne($duplicate);
    $requests = []; $fake();
    verify($run() === 0 && $matches->countDocuments(['season_id'=>$season]) === 240, 'Legacy exact duplicate removed');
    verify(count($requests) === 0, 'Duplicate repair does not refetch complete season');
    $all = $matches->find(['season_id'=>$season], ['typeMap'=>['root'=>'array','document'=>'array','array'=>'array']])->toArray();
    verify((new SeasonIntegrity)->completeSeason($all,30,8), 'Full integrity check');
    echo "Season collection database checks passed.\n";
} finally { $matches->deleteMany(['season_id'=>$season]); }
