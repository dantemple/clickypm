<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGitHubWebhook;
use App\Support\GitHubApp;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class GitHubWebhookController extends Controller
{
    /**
     * Take a webhook delivery from GitHub, check it's genuine, and queue it.
     * GitHub retries deliveries it isn't sure about, so each is only
     * processed once.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless(GitHubApp::configured(), 404);
        abort_unless(GitHubApp::verifySignature($request->getContent(), $request->header('X-Hub-Signature-256')), 403);

        $delivery = $request->header('X-GitHub-Delivery');

        if (is_string($delivery) && $delivery !== '' && ! Cache::add("github:delivery:{$delivery}", true, now()->addDay())) {
            return response()->noContent();
        }

        $payload = $request->isJson()
            ? $request->json()->all()
            : (json_decode((string) $request->input('payload', '{}'), true) ?: []);

        ProcessGitHubWebhook::dispatch((string) $request->header('X-GitHub-Event', ''), $payload);

        return response()->noContent(202);
    }
}
