<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CustomLogin extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent()
                ->prefixIcon('heroicon-m-envelope')
                ->hiddenLabel(),

            $this->getPasswordFormComponent()
                ->prefixIcon('heroicon-m-lock-closed')
                ->hiddenLabel(),

            $this->getRememberFormComponent()
                ->tooltip('مرا بخاطر بسپار شود')
                ->label(new HtmlString('
                    <svg aria-hidden="true" style="margin: 0; padding: 0; height: 24px; width: 24px; color: #9ca3af;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10" cy="10" r="4"></circle>
                        <path d="M14 10h7v2h-7v2h-2v-4h2z"></path>
                    </svg>
                ')),
        ]);
    }

    public function getHeading(): string|Htmlable
    {
        $appName = e(config('app.name'));

        return new HtmlString("
            <div style=\"display: flex; flex-direction: column; align-items: center; justify-content: center;\">
                <span class=\"fi-login-brand\">{$appName}</span>
            </div>
        ");
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('
            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 0.75rem; line-height: 1rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; color: #4b5563;">
                    Trade
                    <span style="position: relative; display: inline-block; opacity: 0.5;">
                        hard
                        <span style="position: absolute; left: 0; top: 50%; height: 2px; width: 100%; transform: translateY(-50%) rotate(8deg); background-color: #ef4444;"></span>
                    </span>
                    <span style="color: #f59e0b;">smart</span>
                </span>
            </div>
        ');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->tooltip('ورود')
            ->label(new HtmlString('
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <svg aria-hidden="true" style="height: 24px; width: 24px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                </div>
            '));
    }
}
