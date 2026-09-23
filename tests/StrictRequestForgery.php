<?php

namespace A2ZWeb\Newsletter\Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * Laravel 13 only: a strict stand-in for the PreventRequestForgery entry in
 * the `web` group. Only load it after checking PreventRequestForgery exists.
 */
class StrictRequestForgery extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
