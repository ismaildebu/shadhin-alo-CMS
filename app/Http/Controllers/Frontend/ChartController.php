<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Chart;
use Illuminate\View\View;

class ChartController extends Controller
{
    public function index(): View
    {
        $charts = Chart::published()
            ->ordered()
            ->paginate(10);

        return view('frontend.charts.index', compact('charts'));
    }
}
