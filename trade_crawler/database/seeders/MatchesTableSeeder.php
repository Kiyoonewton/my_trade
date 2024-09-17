<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class MatchesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFilePath = database_path('matches.csv');
        $csv = Reader::createFromPath($csvFilePath, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();
        foreach ($records as $record) {
            $maxId = DB::table('matches')->max('id');
            
            // Generate the next ID within the 3-digit range
            $nextId = ($maxId === null) ? '001' : str_pad((int) $maxId + 1, 3, '0', STR_PAD_LEFT);

            DB::table('matches')->insert([
                'id' => $nextId,
                'team1' => $record['team1'],
                'team2' => $record['team2'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
