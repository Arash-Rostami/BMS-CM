<?php

namespace App\Notifications;

use App\Models\NotificationSetting;
use App\Services\PermissionLabeler;
use Illuminate\Notifications\Messages\MailMessage;

class ModelEventEmail extends BaseModelEventNotification
{
    public function toMail($notifiable): MailMessage
    {
        $params = [
            'model' => PermissionLabeler::getEntityLabel(get_class($this->model)),
            'identifier' => $this->getModelIdentifier(),
        ];

        $mail = (new MailMessage)
            ->subject($this->line('subject.'.$this->actionKey(), $params))
            ->greeting($this->line('greeting', ['name' => $notifiable->name]))
            ->line($this->line('intro.'.$this->actionKey(), $params));

        if ($this->action === 'update' && ! empty($this->changes)) {
            $mail->line($this->line('changes'));
            foreach ($this->changes as $column => $change) {
                $mail->line('• **'.NotificationSetting::columnLabel([$this->model->getTable()], $column).':** '.$this->formatValue($change['old']).' → '.$this->formatValue($change['new']));
            }
        }

        if (! empty($this->setting->notes)) {
            $mail->line('')->line($this->line('notes'))->line($this->setting->notes);
        }

        return $mail
            ->action(__(self::STRINGS.'action_view'), rtrim((string) config('app.url'), '/').$this->getRecordUrl())
            ->line($this->line('outro'));
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    private function line(string $suffix, array $params = []): string
    {
        return __(self::STRINGS.'mail.'.$suffix, $params);
    }

    private function formatValue($value): string
    {
        if (is_null($value)) {
            return $this->line('empty');
        }
        if (is_bool($value)) {
            return $this->line($value ? 'yes' : 'no');
        }
        if ($value instanceof \DateTimeInterface || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value))) {
            return $this->formatDate($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function formatDate($value): string
    {
        $value = $value instanceof \DateTimeInterface ? \DateTime::createFromInterface($value) : $value;
        $withTime = is_string($value) ? str_contains($value, ':') : $value->format('His') !== '000000';

        return app()->isLocale('fa') ? toPersianDate($value, $withTime) : toGregorianDate($value, $withTime);
    }
}
