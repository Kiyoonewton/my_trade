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
        $counter = (int) DB::table('matches')->max('id');
        foreach ($records as $record) {
            $counter++;
            $nextId = str_pad($counter, 3, '0', STR_PAD_LEFT);

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
