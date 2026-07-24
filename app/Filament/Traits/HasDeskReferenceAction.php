<?php

namespace App\Filament\Traits;

use App\Models\DeskReference;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasDeskReferenceAction
{
    public static function getDeskReferenceHeaderAction(): ?Action
    {
        $key = Str::camel(class_basename(static::getModel()));
        $entry = config("desk-reference.{$key}");

        if (! $entry) {
            return null;
        }

        $group = $entry['group'];

        if (! Lang::has("deskReference/{$group}")) {
            return null;
        }

        $content = trans("deskReference/{$group}");

        if (empty($content['terms']) && empty($content['process']) && empty($content['dos']) && empty($content['donts']) && empty($content['tips'])) {
            return null;
        }

        $version = $entry['version'];

        $seen = DeskReference::query()
            ->where('user_id', auth()->id())
            ->where('group_key', $group)
            ->where('version', $version)
            ->exists();

        return Action::make('deskReference')
            ->label(__('resources/general/strings.desk_reference.action_label'))
            ->icon($entry['icon'] ?? 'heroicon-o-book-open')
            ->color($seen ? 'gray' : 'warning')
            ->extraAttributes($seen ? [] : ['class' => 'dr-unread'])
            ->modalHeading($content['tab_label'] ?? __('resources/general/strings.desk_reference.modal_heading'))
            ->modalContent(view('filament.desk-reference.panel', [
                'content' => $content,
                'group' => $group,
                'version' => $version,
                'currentModule' => $key,
            ]))
            ->modalSubmitAction(false)
            ->modalWidth('4xl');
    }
}
