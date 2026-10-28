<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;

/**
 * Turns the three article-form selects (division / district / upazila) into the one location the
 * article is about, and decides whether this user may report for it.
 */
final class LocationResolver
{
    /**
     * @return array{location: ?Location, error: ?string}
     */
    public static function resolve(?int $divisionId, ?int $districtId, ?int $upazilaId): array
    {
        $division = $divisionId ? Location::divisions()->find($divisionId) : null;
        $district = $districtId ? Location::districts()->find($districtId) : null;
        $upazila = $upazilaId ? Location::upazilas()->find($upazilaId) : null;

        if (($divisionId && ! $division) || ($districtId && ! $district) || ($upazilaId && ! $upazila)) {
            return ['location' => null, 'error' => 'নির্বাচিত লোকেশনটি পাওয়া যায়নি।'];
        }

        // The selections must describe ONE place: upazila inside district inside division.
        if ($upazila && $district && $upazila->parent_id !== $district->id) {
            return ['location' => null, 'error' => 'নির্বাচিত উপজেলা এই জেলার অন্তর্গত নয়।'];
        }

        if ($district && $division && $district->parent_id !== $division->id) {
            return ['location' => null, 'error' => 'নির্বাচিত জেলা এই বিভাগের অন্তর্গত নয়।'];
        }

        if ($upazila && ! $district && $division && $upazila->parent?->parent_id !== $division->id) {
            return ['location' => null, 'error' => 'নির্বাচিত উপজেলা এই বিভাগের অন্তর্গত নয়।'];
        }

        // The most specific choice wins.
        return ['location' => $upazila ?? $district ?? $division, 'error' => null];
    }

    /** May $user report for $location? (Only district correspondents are restricted.) */
    public static function allows(User $user, ?Location $location): bool
    {
        if (! $user->isDistrictCorrespondent()) {
            return true;
        }

        if ($location === null) {
            return false;
        }

        return $location->id === $user->district_id
            || ($location->isUpazila() && $location->parent_id === $user->district_id);
    }
}
