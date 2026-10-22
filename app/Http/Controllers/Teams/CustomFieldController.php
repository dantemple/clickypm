<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Teams\SaveCustomField;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveCustomFieldRequest;
use App\Models\CustomField;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CustomFieldController extends Controller
{
    /**
     * Add a custom field to the workspace for its projects to use.
     */
    public function store(SaveCustomFieldRequest $request, Team $team, SaveCustomField $save): RedirectResponse
    {
        Gate::authorize('update', $team);

        if ($team->customFields()->count() >= CustomField::MAX_PER_TEAM) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('A workspace can have up to :count custom fields.', ['count' => CustomField::MAX_PER_TEAM])]);

            return back();
        }

        $save->create(
            $team,
            $request->validated('name'),
            CustomFieldType::from($request->validated('type')),
            $request->boolean('hidden_from_clients'),
            $request->validated('options'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field added.')]);

        return back();
    }

    /**
     * Rename a field, change its options or who can see it.
     */
    public function update(SaveCustomFieldRequest $request, Team $team, CustomField $customField, SaveCustomField $save): RedirectResponse
    {
        Gate::authorize('update', $team);

        $save->update($customField, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field updated.')]);

        return back();
    }

    /**
     * Delete a field, removing it and its values from every project.
     */
    public function destroy(Team $team, CustomField $customField, SaveCustomField $save): RedirectResponse
    {
        Gate::authorize('update', $team);

        $save->delete($customField);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field deleted.')]);

        return back();
    }
}
