<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewsTransitionRequest;
use App\Models\News;
use App\Services\NewsWorkflow;
use Illuminate\Http\RedirectResponse;

class NewsWorkflowController extends Controller
{
    public function store(NewsTransitionRequest $request, News $news): RedirectResponse
    {
        // Throws AuthorizationException (403) / ValidationException as appropriate.
        NewsWorkflow::transition(
            $news,
            $request->user(),
            $request->validated('to'),
            $request->validated('note'),
            $request->publishAt(),
        );

        return back()->with('success', 'অবস্থা বদলে হয়েছে: '.$news->status_label);
    }
}
