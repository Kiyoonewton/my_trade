<?php

namespace App\Trait;
trait TeamArrangerTrait
{
    /**
     * Get all combinations of 3 teams from the collection
     */
    public function getCombinations(array $teams, int $groupSize): array
    {
        $combinations = [];
        $this->combine($teams, [], count($teams), $groupSize, 0, $combinations);
        return $combinations;
    }

    /**
     * Helper function to generate combinations
     */
    private function combine(array $teams, array $current, int $n, int $groupSize, int $index, array &$combinations): void
    {
        if (count($current) === $groupSize) {
            $combinations[] = $current;
            return;
        }

        for ($i = $index; $i < $n; $i++) {
            $this->combine($teams, array_merge($current, [$teams[$i]]), $n, $groupSize, $i + 1, $combinations);
        }
    }

    /**
     * Generate 560 unique groupings of teams into groups of 3
     */
    public function generate560Groupings(array $teamsCollections): array
    {
        // Get all combinations of 3 teams
        $allCombinations = $this->getCombinations($teamsCollections, 3);

        // Shuffle to get random groupings
        shuffle($allCombinations);

        // We will store unique arrangements
        $uniqueArrangements = [];

        // Continue until we have 560 unique arrangements
        while (count($uniqueArrangements) < 560) {
            // Create random groupings
            $grouping = [];
            $remainingTeams = $teamsCollections;

            // Create the groups of 3 and add them to the grouping
            while (count($remainingTeams) >= 3) {
                $group = array_splice($remainingTeams, 0, 3);
                $grouping[] = $group;

                // Log each grouping as it's formed
                echo 'Grouping formed: ' . json_encode($group) . PHP_EOL;
            }

            // Ensure unique groupings
            $serializedGrouping = serialize($grouping);
            if (!in_array($serializedGrouping, $uniqueArrangements)) {
                $uniqueArrangements[] = $serializedGrouping;
            }
        }

        // Unserialize back to array format
        return array_map('unserialize', $uniqueArrangements);
    }
}
