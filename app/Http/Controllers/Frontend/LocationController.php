<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\News;
use Illuminate\View\View;

class LocationController extends Controller
{
    /**
     * /location/{division}                      all news of the division, its districts and upazilas
     * /location/{division}/{district}           all news of the district and its upazilas
     * /location/{division}/{district}/{upazila} news of that upazila
     */
    public function show(string $division, ?string $district = null, ?string $upazila = null): View
    {
        $location = Location::divisions()->where('slug', $division)->firstOrFail();

        if ($district !== null) {
            $location = $location->children()->where('slug', $district)->firstOrFail();
        }

        if ($upazila !== null) {
            $location = $location->children()->where('slug', $upazila)->firstOrFail();
        }

        $news = News::published()
            ->whereIn('location_id', $location->descendantIds())
            ->with(['category', 'location'])
            ->latestPublished()
            ->paginate(12)
            ->withQueryString();

        return view('frontend.location', [
            'location' => $location,
            'path' => $location->path(),
            // districts of a division, upazilas of a district; an upazila has none
            'children' => $location->isUpazila() ? collect() : $location->children()->get(),
            'news' => $news,
        ]);
    }
}
