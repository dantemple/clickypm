<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\AcceptPendingInvitations;
use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam, private AcceptPendingInvitations $acceptInvitations)
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

            // People who were invited (e.g. clients) join those workspaces instead
            // of getting their own. They're let in once they verify the address
            // (see AcceptInvitationsOnVerification), so nobody can claim an
            // invitation just by signing up with someone else's email.
            if (! $this->acceptInvitations->pendingFor($user->email)->exists()) {
                $this->createTeam->handle($user, $user->name."'s Workspace", isPersonal: true);
            }

            return $user;
        });
    }
}
