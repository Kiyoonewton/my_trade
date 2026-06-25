# my_trade

$seasonId = '3051915'; // or any new season ID

// Step 1: Fetch and save data
(new \App\Jobs\ProcessWinOrDraw($seasonId))->handle();

// Step 2: Run analysis

$seasonId = '3054457';
(new \App\Jobs\ProcessWinOrDraw($seasonId))->handle();

$q = new \App\GraphQL\Queries\Get2ndIn16WithFirstAppearance();
$result = $q(null, ['seasonId' => $seasonId]);
$result;
