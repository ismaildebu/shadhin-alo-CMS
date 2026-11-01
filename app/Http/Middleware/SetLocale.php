<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $availableLocales = ['bn', 'en'];

        if ($request->has('lang') && in_array($request->get('lang'), $availableLocales)) {
            $locale = $request->get('lang');
            Session::put('locale', $locale);
        } elseif (Session::has('locale') && in_array(Session::get('locale'), $availableLocales)) {
            $locale = Session::get('locale');
        } else {
            $locale = config('app.locale', 'bn');
        }

        App::setLocale($locale);

        return $next($request);
    }
}