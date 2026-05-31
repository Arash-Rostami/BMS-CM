<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class ModelEventEmail extends BaseModelEventNotification
{
    public function toMail($notifiable): MailMessage
    {
        $modelName = Str::headline(class_basename($this->model));
        $identifier = $this->getModelIdentifier();
        $tableName = $this->model->getTable();
        $recordId = $this->model->getKey();

        $mail = (new MailMessage)
            ->subject($this->getSubject($modelName, $identifier))
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getIntroLine($modelName, $identifier));

        if ($this->action === 'update' && !empty($this->changes)) {
            $mail->line('**Changes:**');
            foreach ($this->changes as $column => $change) {
                $columnName = Str::headline($column);
                $oldValue = $this->formatValue($change['old']);
                $newValue = $this->formatValue($change['new']);
                $mail->line("• **{$columnName}:** {$oldValue} → {$newValue}");
            }
        }

        if (!empty($this->setting->notes)) {
            $mail->line('')->line('**Additional Notes:**')->line($this->setting->notes);
        }

        $url = config('app.url') . "/dashboard/{$tableName}/{$recordId}/edit";
        $mail->action('View Record', $url);

        return $mail->line('This notification was sent based on your notification settings.');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    private function formatValue($value): string
    {
        if (is_null($value)) return '(empty)';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if ($value instanceof \DateTime) return $value->format('Y-m-d H:i');

        return (string)$value;
    }
}
