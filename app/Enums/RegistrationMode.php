<?php

namespace App\Enums;

enum RegistrationMode: string
{
    /** Anyone can sign up. */
    case Open = 'open';

    /** Only addresses with a pending workspace invitation can sign up. */
    case InviteOnly = 'invite';

    /**
     * The mode the app is configured with (REGISTRATION_MODE).
     */
    public static function current(): self
    {
        return self::from(config('app.registration', self::Open->value));
    }

    /**
     * Determine if sign-ups need an invitation.
     */
    public static function isInviteOnly(): bool
    {
        return self::current() === self::InviteOnly;
    }
}
