<?php

namespace Database\Factories;

use App\Models\MatchDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchDay>
 */
class MatchDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

     protected $model = MatchDay::class;
    public function definition(): array
    {
        return [
            'queryUrl' => 'vfc_stats_round_odds2/vf:season:' . $this->faker->unique()->numberBetween(1000000, 9999999) . '/24',
            'doc' => [
                [
                    'timestamp' => now()->timestamp,
                    'data' => [
                        'odds' => [
                            [
                                'id' => 'vf:match:' . $this->faker->unique()->numberBetween(1000000000, 9999999999),
                                'sport_id' => 'sr:sport:1',
                                'tournament_id' => 'vf:tournament:' . $this->faker->numberBetween(1000, 9999),
                                'teams' => [
                                    'home' => [
                                        'id' => 'sr:competitor:' . $this->faker->unique()->numberBetween(100000, 999999),
                                        'name' => $this->faker->word,
                                        'abbr' => strtoupper(Str::random(3)),
                                    ],
                                    'away' => [
                                        'id' => 'sr:competitor:' . $this->faker->unique()->numberBetween(100000, 999999),
                                        'name' => $this->faker->word,
                                        'abbr' => strtoupper(Str::random(3)),
                                    ],
                                ],
                                'market' => [
                                    [
                                        'id' => $this->faker->numberBetween(1, 100),
                                        'name' => $this->faker->word,
                                        'status' => $this->faker->numberBetween(-5, 5),
                                        'outcome' => [
                                            [
                                                'id' => $this->faker->unique()->numberBetween(100, 999),
                                                'name' => $this->faker->word,
                                                'abbr' => strtoupper(Str::random(3)),
                                                'odds' => $this->faker->randomFloat(2, 1, 100),
                                                'result' => $this->faker->numberBetween(0, 1),
                                                'active' => $this->faker->numberBetween(0, 1),
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
