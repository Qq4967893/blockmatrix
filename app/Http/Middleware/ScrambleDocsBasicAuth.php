<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ScrambleDocsBasicAuth
{
    /**
     * Minimum password length before the middleware considers it too weak.
     */
    private const MIN_PASSWORD_LENGTH = 8;

    public function handle(Request $request, Closure $next): Response
    {
        $expectedUsername = (string) config('scramble.docs_basic_auth.username', '');
        $expectedPassword = (string) config('scramble.docs_basic_auth.password', '');

        if ($expectedUsername === '' || $expectedPassword === '') {
            return $this->unauthorizedResponse();
        }

        if (! $this->isCredentialStrongEnough($expectedUsername, $expectedPassword)) {
            Log::warning('Scramble docs credentials are too weak; update SCRAMBLE_DOCS_PASSWORD to a stronger value.');

            return $this->unauthorizedResponse();
        }

        $providedUsername = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();

        if (
            ! hash_equals($expectedUsername, $providedUsername)
            || ! hash_equals($expectedPassword, $providedPassword)
        ) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function isCredentialStrongEnough(string $username, string $password): bool
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }

        if (hash_equals($username, $password)) {
            return false;
        }

        return true;
    }

    private function unauthorizedResponse(): Response
    {
        return response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="API Docs"',
        ]);
    }
}
