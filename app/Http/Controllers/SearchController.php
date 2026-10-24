<?php

namespace App\Http\Controllers;

use App\Actions\Search\SearchWorkspace;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Search the workspace for the command palette.
     */
    public function __invoke(Request $request, Team $current_team, SearchWorkspace $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($search->handle($request->user(), $current_team, $validated['q'] ?? ''));
    }
}
