<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateArticleDesignRequest;
use App\Support\ArticleDesign;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ArticleDesignController extends Controller
{
    public function edit(): View
    {
        return view('admin.article-design', [
            'design' => ArticleDesign::all(),
            'rows' => ArticleDesign::sidebarRows(),
        ]);
    }

    public function update(UpdateArticleDesignRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $values = [
            'article_layout' => $data['layout'],
            'article_sidebar_side' => $data['side'],
            'article_font' => $data['font'],
            'article_title_size' => $data['title_size'],
            'article_body_size' => $data['body_size'],
            'article_body_width' => $data['body_width'],
            'article_leading' => $data['leading'],
            'article_image_ratio' => $data['image_ratio'],
            'article_image_fit' => $data['image_fit'],
            'article_accent' => strtolower($data['accent']),
            'article_link' => strtolower($data['link']),
        ];

        foreach (array_keys(ArticleDesign::TOGGLES) as $key) {
            $values['article_show_'.$key] = $request->boolean('show_'.$key) ? '1' : '0';
        }

        foreach (array_keys(ArticleDesign::COUNTS) as $key) {
            $values['article_'.$key.'_count'] = (string) (int) $data[$key.'_count'];
        }

        $values += ArticleDesign::sidebarValues($data['widgets']);

        // Saves every value, clears the settings cache and the frontend cache.
        Settings::set($values);

        return back()->with('success', 'সংবাদ পেজের ডিজাইন সংরক্ষণ হয়েছে।');
    }
}
