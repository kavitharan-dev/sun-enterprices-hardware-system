<?php

namespace App\Listeners;

use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    public function login(Login $event): void
    {
        ActivityLogger::log(
            'login',
            'Auth',
            "{$event->user->name} logged in",
            $event->user,
            ['email' => $event->user->email],
            $event->user->id,
        );
    }

    public function logout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        ActivityLogger::log(
            'logout',
            'Auth',
            "{$event->user->name} logged out",
            $event->user,
            null,
            $event->user->id,
        );
    }

    public function failed(Failed $event): void
    {
        ActivityLogger::log(
            'login_failed',
            'Auth',
            'Failed login attempt',
            null,
            ['email' => $event->credentials['email'] ?? null],
        );
    }
}
