<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Logout;


class UpdateUserLastLogout
{

    public function handle(Logout $event): void
    {
        if ($event->user instanceof User) {
            $event->user->last_log_out = now();
            $event->user->save();
        }
    }
}
