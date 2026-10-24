<?php

namespace App\Http\Controllers;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;

class HomeController extends Controller
{
    use RedirectsToCurrentTeam;

    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->user()) {
            return to_route('login');
        }

        return redirect($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
