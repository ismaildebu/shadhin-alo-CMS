<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationChildrenController extends Controller
{
    /**
     * Children of one place (division -> districts -> upazilas) for the home-page filter.
     * Each entry carries the public URL of that place, so the browser can simply go there.
     */
    public function __invoke(int $location): JsonResponse
    {
        $parent = Location::findOrFail($location);
        $parent->loadMissing('parent');

        $children = $parent->children()->orderBy('name_bn')->get();

        // url() walks up the tree; hand every child its parent so that costs no extra queries.
        $children->each(fn (Location $child) => $child->setRelation('parent', $parent));

        return response()->json(
            $children->map(fn (Location $child) => [
                'id' => $child->id,
                'name' => $child->name_bn,
                'url' => $child->url(),
            ])->values(),
            200,
            ['Cache-Control' => 'public, max-age=3600'],
        );
    }
}
