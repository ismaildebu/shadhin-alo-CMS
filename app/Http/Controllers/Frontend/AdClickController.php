<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\RedirectResponse;

class AdClickController extends Controller
{
    public function __invoke(int $id): RedirectResponse
    {
        $ad = Ad::find($id);

        if (! $ad || $ad->type !== 'image' || ! $ad->link_url || ! $ad->isLive()) {
            return redirect()->route('home');
        }

        Ad::whereKey($ad->id)->increment('clicks');

        return redirect()->away($ad->link_url);
    }
}