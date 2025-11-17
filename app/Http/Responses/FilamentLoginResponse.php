<?php

namespace App\Http\Responses;

use App\Filament\Pages\LandingPage;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\LoginResponse as BaseLoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;


class FilamentLoginResponse extends BaseLoginResponse implements LoginResponseContract
{
    /**
     * Return RedirectResponse or Livewire Redirector for Filament v4.
     *
     * @param \Illuminate\Http\Request $request
     * @return RedirectResponse|Redirector
     */
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->to(LandingPage::getUrl());
    }
}
