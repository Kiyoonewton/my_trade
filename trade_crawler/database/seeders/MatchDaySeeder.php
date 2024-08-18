<?php

namespace Database\Seeders;

use App\Models\MatchDay;
use Illuminate\Database\Seeder;

class MatchDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MatchDay::factory()->count(10)->create();
    }
}
