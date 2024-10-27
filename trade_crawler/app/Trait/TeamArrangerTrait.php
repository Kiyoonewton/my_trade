<?php

namespace App\Trait;

use App\Models\TeamArranger;

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

        // We will store unique arrangements
        $uniqueArrangements = [];

        // Loop through all combinations and add them to unique arrangements if they don't already exist
        foreach ($allCombinations as $grouping) {
            // Serialize the grouping to ensure uniqueness
            $serializedGrouping = serialize($grouping);
            
            // Only add to uniqueArrangements if it doesn't already exist
            if (!in_array($serializedGrouping, $uniqueArrangements)) {
                $uniqueArrangements[] = $serializedGrouping;
            }
            // print_r(array_map('unserialize', $serializedGrouping));
        }



        // Unserialize back to array format
        return array_map('unserialize', $uniqueArrangements);
    }
}
