<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ArticleVisuals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisualBuilderController extends Controller
{
    public function index(): View
    {
        return view('admin.visual-builder');
    }

    /**
     * Renders a shortcode exactly like the public article page does.
     * The input is escaped first, so only recognised shortcodes turn into markup.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shortcode' => ['required', 'string', 'max:4000'],
        ], [
            'shortcode.required' => 'শর্টকোড খালি।',
            'shortcode.max' => 'শর্টকোড খুব বড়।',
        ]);

        return response()->json([
            'html' => ArticleVisuals::expand(e($data['shortcode'])),
        ]);
    }
}
