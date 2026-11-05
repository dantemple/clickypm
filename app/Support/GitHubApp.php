<?php

namespace App\Support;

/**
 * The GitHub App this server is paired with (see .env.example). Workspaces
 * install it on GitHub; GitHub then sends us webhooks about their repos.
 */
final class GitHubApp
{
    /**
     * Whether the server has a GitHub App to offer.
     */
    public static function configured(): bool
    {
        return filled(config('services.github.app_slug')) && filled(config('services.github.webhook_secret'));
    }

    /**
     * Where to send someone to install the app. GitHub passes the state back
     * to the setup URL so the installation can be matched to a workspace.
     */
    public static function installUrl(string $state): string
    {
        return 'https://github.com/apps/'.config('services.github.app_slug').'/installations/new?'.http_build_query(['state' => $state]);
    }

    /**
     * Whether a webhook body really came from GitHub: its signature header
     * must be the HMAC of the raw body under the shared secret.
     */
    public static function verifySignature(string $body, ?string $header): bool
    {
        $secret = config('services.github.webhook_secret');

        if (! is_string($secret) || $secret === '' || ! is_string($header)) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $header);
    }
}
