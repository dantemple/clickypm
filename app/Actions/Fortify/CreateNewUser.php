<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            // People who were invited (e.g. clients) join those workspaces instead of getting their own.
            if (! $this->acceptPendingInvitations($user)) {
                $this->createTeam->handle($user, $user->name."'s Workspace", isPersonal: true);
            }

            return $user;
        });
    }

    /**
     * Accept every open invitation sent to the user's email address.
     *
     * Workspace routes still require a verified email, so an unverified
     * sign-up cannot use the membership until they prove they own the address.
     */
    private function acceptPendingInvitations(User $user): bool
    {
        $invitations = TeamInvitation::query()
            ->with('team')
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->oldest()
            ->get();

        foreach ($invitations as $invitation) {
            $invitation->team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $invitation->role],
            );

            $invitation->update(['accepted_at' => now()]);
        }

        if ($invitations->isEmpty()) {
            return false;
        }

        $user->switchTeam($invitations->first()->team);

        return true;
    }
}
