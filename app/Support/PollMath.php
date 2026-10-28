<?php

namespace App\Support;

final class PollMath
{
    /**
     * Whole-number percentages that add up to 100 (largest remainder), or all zeros when nobody has voted.
     *
     * @param  array<int|string, int>  $counts  option id => votes
     * @return array<int|string, int>           option id => percent, in the same order
     */
    public static function percentages(array $counts): array
    {
        $total = array_sum($counts);

        if ($total <= 0) {
            return array_map(fn () => 0, $counts);
        }

        $base = [];
        $remainder = [];

        foreach ($counts as $id => $count) {
            $exact = $count * 100 / $total;
            $base[$id] = (int) floor($exact);
            $remainder[$id] = $exact - $base[$id];
        }

        $left = 100 - array_sum($base);
        arsort($remainder);

        foreach (array_keys($remainder) as $id) {
            if ($left <= 0) {
                break;
            }

            $base[$id]++;
            $left--;
        }

        return $base;
    }
}
