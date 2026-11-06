<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FacebookPost;
use App\Support\FacebookShare;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "ফেসবুক পোস্ট" — প্রকাশিত খবর পেজের টাইমলাইনে পাঠানোর পুরো হলে এখানেই।
 *
 * টিক করা ক্যাটাগরির খবর আপনি চলে; বাকি সব "অপেক্ষমাণ" তালিকায় পাঠান বোতামের
 * অপেক্ষায় থাকে। কার্ড না থাকলে লাইনটি "কার্ড লাগবে" অবস্থায় দাঁড়িয়ে থাকে।
 */
class FacebookShareController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-facebook');

        return view('admin.facebook', [
            'enabled' => Settings::get('fb_enabled') === '1',
            'message' => Settings::get('fb_message'),
            'comment' => Settings::get('fb_comment'),
            'defaults' => ['message' => Settings::DEFAULTS['fb_message'], 'comment' => Settings::DEFAULTS['fb_comment']],
            'pageId' => (string) config('services.facebook.page_id', ''),
            'hasToken' => trim((string) config('services.facebook.page_token', '')) !== '',
            'version' => (string) config('services.facebook.graph_version', 'v23.0'),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'fb_auto']),
            'waiting' => $this->posts([FacebookPost::NEEDS_CARD, FacebookPost::QUEUED, FacebookPost::FAILED]),
            'sent' => $this->posts([FacebookPost::POSTED]),
            'counts' => FacebookPost::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'ready' => FacebookShare::ready(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-facebook');

        $data = $request->validate([
            'fb_enabled' => ['required', 'in:0,1'],
            'fb_message' => ['required', 'string', 'max:2000'],
            'fb_comment' => ['required', 'string', 'max:1000'],
            'fb_categories' => ['nullable', 'array'],
            'fb_categories.*' => ['integer', Rule::exists('categories', 'id')],
        ], [
            'fb_message.required' => 'পোস্টের লেখা ফাঁকা রাখা যাবে না।',
            'fb_comment.required' => 'কমেন্টের লেখা ফাঁকা রাখা যাবে না; শুধু {url} লিখে রাখুন।',
            'fb_categories.*.exists' => 'ক্যাটাগরিগুলোর একটি আর নেই। তালিকা নতুন করে খুলে আবার টিক দিন।',
        ]);

        Settings::set([
            'fb_enabled' => $data['fb_enabled'],
            'fb_message' => $data['fb_message'],
            'fb_comment' => $data['fb_comment'],
        ]);

        $ids = array_map('intval', $data['fb_categories'] ?? []);

        Category::query()->whereIn('id', $ids)->update(['fb_auto' => true]);
        Category::query()->whereNotIn('id', $ids)->update(['fb_auto' => false]);

        return back()->with('success', 'ফেসবুক পোস্টের সেটিংস সংরক্ষণ হয়েছে।');
    }

    /** টোকেন ও পেজ ঠিক আছে কি না ফেসবুক থেকে জেনে নেওয়া। */
    public function verify(): RedirectResponse
    {
        Gate::authorize('manage-facebook');

        $result = FacebookShare::verify();

        return $result['ok']
            ? back()->with('success', $result['message'])
            : back()->withErrors(['facebook' => $result['message']]);
    }

    /** অ্যাপ্রুয়াল লিস্টের "পাঠান": ছাড়পত্র ছাড়াই এই লাইনটি এখনই ফেসবুকে যায়। */
    public function send(Request $request, FacebookPost $post): RedirectResponse
    {
        Gate::authorize('manage-facebook');

        return $this->respond(FacebookShare::send($post, $request->user()));
    }

    /** "আবার পাঠান": পোস্টটি নতুন করে ফেসবুকে তোলা হয় (আগের পোস্ট মুছে যায় না)। */
    public function resend(Request $request, FacebookPost $post): RedirectResponse
    {
        Gate::authorize('manage-facebook');

        return $this->respond(FacebookShare::resend($post, $request->user()));
    }

    public function destroy(FacebookPost $post): RedirectResponse
    {
        Gate::authorize('manage-facebook');

        $post->delete();

        return back()->with('success', 'সারিটি মোছা হয়েছে; ফেসবুকের পোস্টটি এখান থেকে মোছা হয়নি।');
    }

    private function respond(FacebookPost $post): RedirectResponse
    {
        if ($post->status === FacebookPost::POSTED) {
            $note = trim((string) $post->error);

            return back()->with('success', $note === '' ? 'ফেসবুকে পোস্ট হয়েছে।' : 'পোস্ট হয়েছে: '.$note);
        }

        return back()->withErrors(['facebook' => $post->error !== '' ? $post->error : 'ফেসবুকে পোস্ট করা যায়নি।']);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function posts(array $statuses): Collection
    {
        return FacebookPost::query()
            ->with(['news:id,title,slug,status,card_image,category_id,published_at', 'news.category:id,name,fb_auto', 'approver:id,name'])
            ->whereIn('status', $statuses)
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }
}
