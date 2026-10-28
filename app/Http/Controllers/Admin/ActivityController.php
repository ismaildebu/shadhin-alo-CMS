<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(): View
    {
        $activities = Activity::with('user')->orderByDesc('id')->paginate(30);

        return view('admin.activity.index', compact('activities'));
    }
}
