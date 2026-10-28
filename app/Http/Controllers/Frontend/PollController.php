<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use App\Support\BanglaTime;
use App\Support\PollMath;
use App\Support\PollVoter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PollController extends Controller
{
    /** Archive: every published poll with its results. */
    public function index(): View
    {
        $polls = Poll::published()
            ->with('options')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('frontend.polls.index', compact('polls'));
    }

    public function show(Poll $poll): View
    {
        abort_unless($poll->isPublished(), 404);

        $poll->load('options');

        return view('frontend.polls.show', compact('poll'));
    }

    public function vote(Request $request, Poll $poll): JsonResponse|RedirectResponse
    {
        abort_unless($poll->isOpen(), 404);

        $data = $request->validate(['option' => ['required', 'integer']]);

        $option = $poll->options()->whereKey($data['option'])->first();

        if (! $option) {
            throw ValidationException::withMessages(['option' => 'ভুল অপশন বাছা হয়েছে।']);
        }

        $token = PollVoter::token($request) ?? (string) Str::uuid();
        $hash = PollVoter::hash($token);
        $already = false;

        try {
            DB::transaction(function () use ($poll, $option, $hash, $request) {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'poll_option_id' => $option->id,
                    'voter_hash' => $hash,
                    'ip_hash' => PollVoter::ipHash($request),
                ]);

                $option->increment('votes_count');
            });
        } catch (UniqueConstraintViolationException) {
            $already = true;   // this browser has voted before; show the results and its earlier choice
        }

        $poll->load('options');
        $mine = PollVote::where('poll_id', $poll->id)->where('voter_hash', $hash)->first();
        $cookie = cookie(PollVoter::COOKIE, $token, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');

        if ($request->expectsJson()) {
            $percent = $poll->percentages();
            $total = $poll->totalVotes();

            return response()->json([
                'already' => $already,
                'selected' => $mine?->poll_option_id,
                'total' => $total,
                'total_text' => BanglaTime::digits($total),
                'options' => $poll->options->map(fn ($o) => [
                    'id' => $o->id,
                    'pct' => $percent[$o->id],
                    'pct_text' => BanglaTime::digits($percent[$o->id]).' %',
                ])->values(),
            ])->withCookie($cookie);
        }

        return back()
            ->withCookie($cookie)
            ->with('poll_notice', $already ? 'আপনি আগেই ভোট দিয়েছেন।' : 'আপনার ভোট গ্রহণ করা হয়েছে।')
            ->with('poll_notice_id', $poll->id);
    }
}
