<?php

namespace App\Services\Imports;

use App\Services\Imports\Stages\AppendUnresolvedMatchNotes;
use App\Services\Imports\Stages\ApplyColumnFallbacks;
use App\Services\Imports\Stages\AttachPivotRelations;
use App\Services\Imports\Stages\PersistChildRows;
use App\Services\Imports\Stages\RejectMissingManualColumns;
use App\Services\Imports\Stages\RunModuleRecalculation;
use App\Services\Imports\Stages\SyncEavAttributes;
use Illuminate\Pipeline\Pipeline;

final class ImportPipeline
{
    public const BEFORE_SAVE_STAGES = [
        ApplyColumnFallbacks::class,
        AppendUnresolvedMatchNotes::class,
        RejectMissingManualColumns::class,
    ];

    public const AFTER_SAVE_STAGES = [
        PersistChildRows::class,
        SyncEavAttributes::class,
        AttachPivotRelations::class,
        RunModuleRecalculation::class,
    ];

    public static function runBeforeSave(ImportRowContext $context): ImportRowContext
    {
        return self::send($context, self::BEFORE_SAVE_STAGES);
    }

    public static function runAfterSave(ImportRowContext $context): ImportRowContext
    {
        return self::send($context, self::AFTER_SAVE_STAGES);
    }

    private static function send(ImportRowContext $context, array $stages): ImportRowContext
    {
        return app(Pipeline::class)
            ->send($context)
            ->through($stages)
            ->then(fn (ImportRowContext $context): ImportRowContext => $context);
    }
}
