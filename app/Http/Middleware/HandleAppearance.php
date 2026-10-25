<?php

namespace App\Http\Middleware;

use App\Enums\ThemeColor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('themeColor', $this->themeColor($request));

        return $next($request);
    }

    /**
     * The sidebar colour saved on the account, set on <html> so it applies
     * before the page scripts run. Guests and new accounts get the default.
     */
    protected function themeColor(Request $request): string
    {
        return ($request->user()->theme_color ?? ThemeColor::Slate)->value;
    }
}
