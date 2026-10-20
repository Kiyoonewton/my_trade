<?php
require __DIR__.'/../app/Services/MatchNormalizer.php';
require __DIR__.'/../app/Services/MatchExport.php';
use App\Services\MatchNormalizer;
use App\Services\MatchExport;
function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$event = ['teams'=>['home'=>['name'=>'A'], 'away'=>['name'=>'B']],
    'market'=>[['id'=>1, 'outcome'=>[
        ['id'=>'1','odds'=>2.1,'result'=>0],
        ['id'=>'2','odds'=>3.2,'result'=>0],
        ['id'=>'3','odds'=>3.8,'result'=>0],
    ]]]];
$normalizer = new MatchNormalizer;
$payload = fn ($event) => ['doc'=>[['data'=>['odds'=>[$event]]]]];
check($normalizer->normalize($payload($event),'s',1)[0]['result'] === null, 'Unsettled must stay null');
foreach (['H','D','A'] as $i=>$expected) {
    $settled = $event; $settled['market'][0]['outcome'][$i]['result'] = 1;
    check($normalizer->normalize($payload($settled),'s',1)[0]['result'] === $expected, 'Outcome mapping');
}
$bad = $event; $bad['market'][0]['outcome'][0]['odds'] = 0;
try { $normalizer->normalize($payload($bad),'s',1); throw new RuntimeException('Invalid odds accepted'); }
catch (InvalidArgumentException $e) {}
$export = new MatchExport;
$legacy = ['_id'=>'fixture-1','home'=>'A, United','away'=>'B','home_odd'=>2.1,'draw_odd'=>3.2,'away_odd'=>3.8,'result'=>'x','matchday_id'=>3];
$dir = sys_get_temp_dir().'/my-trade-'.bin2hex(random_bytes(5)); mkdir($dir);
try {
    foreach (['csv','json'] as $format) {
        $path = $dir.'/matches.'.$format;
        check($export->write([$legacy],$path,$format) === 1, 'Row count');
        clearstatcache(true, $path);
        check((fileperms($path) & 0777) === 0644, 'Exports must be host-readable, not world-writable');
        if ($format === 'json') {
            $rows = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
            check($rows[0]['result'] === 'D' && $rows[0]['home_odds'] === 2.1, 'Legacy mapping');
            check($rows[0]['kickoff'] === null, 'Do not invent metadata');
        } else {
            $f = fopen($path,'r'); $headers = fgetcsv($f); $row = array_combine($headers,fgetcsv($f)); fclose($f);
            check($row['home_team'] === 'A, United' && $row['matchday'] === '3', 'CSV escaping and mapping');
        }
        try { $export->write([],$path,$format); throw new RuntimeException('Overwrite allowed'); }
        catch (RuntimeException $e) { check(str_contains($e->getMessage(),'already exists'), 'Overwrite rejected'); }
    }
    $export->write([],$dir.'/empty.json','json');
    check(json_decode(file_get_contents($dir.'/empty.json'),true) === [], 'Empty JSON');
    $broken = (function () use ($legacy) { yield $legacy; throw new RuntimeException('Interrupted'); })();
    try { $export->write($broken,$dir.'/broken.json','json'); } catch (RuntimeException $e) {}
    check(!file_exists($dir.'/broken.json'), 'Partial export not published');
    check(count(glob($dir.'/.export-*')) === 0, 'Temporary file cleanup');
    echo "Data pipeline checks passed.\n";
} finally {
    foreach (glob($dir.'/*') as $path) { unlink($path); } rmdir($dir);
}
