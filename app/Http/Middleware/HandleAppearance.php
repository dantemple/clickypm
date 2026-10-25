<?php

namespace App\Http\Middleware;

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
     * The sidebar colour the user picked, set on <html> so it applies before
     * the page scripts run. Unknown names fall back to the default; the
     * stylesheet only knows the names the picker offers.
     */
    protected function themeColor(Request $request): string
    {
        $color = $request->cookie('theme_color');

        return is_string($color) && preg_match('/^[a-z]{1,20}$/', $color) ? $color : 'slate';
    }
}
