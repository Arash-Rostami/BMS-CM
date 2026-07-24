<?php

namespace App\Filament\Traits;

use App\Models\DeskReference;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait HasDeskReferenceTab
{
    public static function getDeskReferenceInfolistTab(): ?Tab
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

        if (empty($content['terms']) && empty($content['process']) && empty($content['dos']) && empty($content['donts'])) {
            return null;
        }

        $version = $entry['version'];

        $seen = DeskReference::query()
            ->where('user_id', auth()->id())
            ->where('group_key', $group)
            ->where('version', $version)
            ->exists();

        return Tab::make($content['tab_label'] ?? __('resources/general/strings.desk_reference.tab_label'))
            ->key('desk-reference')
            ->icon($entry['icon'] ?? 'heroicon-o-book-open')
            ->badge($seen ? null : '●')
            ->badgeColor('warning')
            ->schema([
                View::make('filament.desk-reference.panel')
                    ->viewData([
                        'content' => $content,
                        'group' => $group,
                        'version' => $version,
                        'currentModule' => $key,
                    ]),
            ]);
    }
}
