<?php

namespace A2ZWeb\Newsletter\Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * The framework skips CSRF checks while running tests; this subclass keeps
 * them on so the one-click route can prove it runs without them.
 */
class StrictCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
