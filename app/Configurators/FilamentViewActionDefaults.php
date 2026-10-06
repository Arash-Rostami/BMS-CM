<?php

namespace App\Configurators;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\View\ActionsRenderHook;
use Filament\Actions\ViewAction;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;

class FilamentViewActionDefaults
{
    public static function configure(): void
    {
        ViewAction::configureUsing(fn (ViewAction $action) => $action
            ->extraModalFooterActions(fn () => [
                DeleteAction::make(),
                CreateAction::make()->icon(Heroicon::Plus),
                EditAction::make(),
            ]));

        FilamentView::registerRenderHook(
            ActionsRenderHook::MODAL_CUSTOM_CONTENT_BEFORE,
            function (array $data): string {
                $action = $data['action'] ?? null;

                if (! $action instanceof ViewAction) {
                    return '';
                }

                $html = collect($action->getExtraModalFooterActions())
                    ->filter(fn ($footerAction) => $footerAction->isVisible())
                    ->map(fn ($footerAction) => (clone $footerAction)->iconButton()->toHtml())
                    ->implode('');

                return $html === '' ? '' : '<div class="fi-modal-header-quick-actions">'.$html.'</div>';
            },
        );
    }
}
