<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Traits;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait HandlesRecipients
{
    protected array $storedRecipients = [
        'to' => [],
        'cc' => [],
    ];

    public static function extractRecipients(array $data): array
    {
        $to = $data['recipients_to'] ?? [];
        $cc = $data['recipients_cc'] ?? [];

        unset($data['recipients_to'], $data['recipients_cc']);

        return [$data, $to, $cc];
    }

    public static function syncRecipients(Model $record, array $to, array $cc): array
    {
        $ccUsers = User::whereIn('name', $cc)->pluck('id', 'name');
        $unresolved = array_values(array_diff($cc, $ccUsers->keys()->all()));

        $syncData = [];

        foreach ($to as $id) {
            $syncData[$id] = ['type' => 'to'];
        }

        foreach ($ccUsers as $id) {
            // 'to' takes precedence if user is in both lists
            if (! isset($syncData[$id])) {
                $syncData[$id] = ['type' => 'cc'];
            }
        }

        $record->recipients()->sync($syncData);

        return $unresolved;
    }

    protected function loadRecipientsToForm(Model $record, array $data): array
    {
        if (! $record->relationLoaded('recipients')) {
            $record->load('recipients');
        }

        $data['recipients_to'] = $record->recipients->where('pivot.type', 'to')->pluck('id')->toArray();
        $data['recipients_cc'] = $record->recipients->where('pivot.type', 'cc')->pluck('name')->toArray();

        return $data;
    }

    protected function parseRecipientsFormData(array $data): array
    {
        $this->storedRecipients['to'] = $data['recipients_to'] ?? [];
        $this->storedRecipients['cc'] = $data['recipients_cc'] ?? [];

        unset($data['recipients_to'], $data['recipients_cc']);

        return $data;
    }

    protected function saveRecipientsToRecord(Model $record): void
    {
        $unresolved = static::syncRecipients($record, $this->storedRecipients['to'], $this->storedRecipients['cc']);

        if ($unresolved !== []) {
            Notification::make()
                ->title(__('resources/correspondence/strings.general.unresolved_cc_warning', ['names' => implode(', ', $unresolved)]))
                ->warning()
                ->send();
        }
    }
}
