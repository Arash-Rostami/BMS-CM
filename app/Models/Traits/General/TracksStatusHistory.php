<?php

namespace App\Models\Traits\General;

use App\Models\Attachment;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait TracksStatusHistory
{
    protected static ?string $pendingStatusHistoryReason = null;

    public static function statusHistoryColumns(): array
    {
        return ['status_id'];
    }

    public static function withStatusHistoryReason(?string $reason): void
    {
        static::$pendingStatusHistoryReason = $reason;
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'statusable');
    }

    protected static function bootTracksStatusHistory(): void
    {
        static::created(function (Model $model) {
            try {
                foreach (static::statusHistoryColumns() as $column) {
                    if (! is_null($model->{$column})) {
                        StatusHistory::create([
                            'statusable_type' => static::class,
                            'statusable_id' => $model->getKey(),
                            'field' => $column,
                            'from_status_id' => null,
                            'to_status_id' => $model->{$column},
                            'user_id' => auth()->id(),
                            'reason' => static::$pendingStatusHistoryReason,
                        ]);
                    }
                }
            } finally {
                static::$pendingStatusHistoryReason = null;
            }
        });

        static::updated(function (Model $model) {
            try {
                foreach (static::statusHistoryColumns() as $column) {
                    if ($model->wasChanged($column)) {
                        StatusHistory::create([
                            'statusable_type' => static::class,
                            'statusable_id' => $model->getKey(),
                            'field' => $column,
                            'from_status_id' => $model->getOriginal($column),
                            'to_status_id' => $model->{$column},
                            'user_id' => auth()->id(),
                            'reason' => static::$pendingStatusHistoryReason,
                        ]);

                        static::archiveAttachmentsIfTerminal($model, $model->{$column});
                    }
                }
            } finally {
                static::$pendingStatusHistoryReason = null;
            }
        });
    }

    protected static function archiveAttachmentsIfTerminal(Model $model, mixed $statusId): void
    {
        if (! $statusId || ! method_exists($model, 'attachments')) {
            return;
        }

        $status = Status::find($statusId, ['id', 'english_type', 'stage_order']);

        if (! $status || ! StatusWorkflow::isTerminal($status)) {
            return;
        }

        $archived = SmartCacheManager::remember(
            'Status',
            ['type' => Attachment::TYPE_ATTACHMENT, 'name' => Attachment::STATUS_ARCHIVED],
            1440,
            fn () => Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_ARCHIVED)
        );

        if ($archived) {
            $model->attachments()->update(['status_id' => $archived->id]);
        }
    }
}
