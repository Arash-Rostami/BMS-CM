<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\LandingPage;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect(LandingPage::getUrl());

    }
}
