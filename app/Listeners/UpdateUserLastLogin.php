<?php

namespace App\Listeners;

use App\Models\User;

use Illuminate\Auth\Events\Login;

class UpdateUserLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->last_log_in = now();
            $event->user->save();
        }
    }
}
