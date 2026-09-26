<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Translates a low-level exception into a short, actionable message a non-technical user
 * can act on, while the full technical detail still reaches storage/logs/laravel.log —
 * tagged with the same reference code shown to the user, so a support request naming the
 * reference is a direct grep, not a guessing game.
 */
class ExceptionPresenter
{
    /**
     * @return array{title: string, body: string, reference: string}
     */
    public static function present(Throwable $e): array
    {
        $reference = static::makeReference();

        Log::error("[{$reference}] ".$e->getMessage(), [
            'reference' => $reference,
            'exception' => $e,
        ]);

        [$title, $body] = static::classify($e);

        return [
            'title' => $title,
            'body' => $body."\n\n".__('errors/strings.notifications.reference_line', ['ref' => $reference]),
            'reference' => $reference,
        ];
    }

    protected static function makeReference(): string
    {
        return 'ERR-'.now()->format('ymd-His').'-'.Str::upper(Str::random(4));
    }

    /**
     * @return array{0: string, 1: string} [title, body]
     */
    protected static function classify(Throwable $e): array
    {
        if ($e instanceof QueryException) {
            return static::classifyQueryException($e);
        }

        if ($e instanceof ModelNotFoundException) {
            return [
                __('errors/strings.notifications.not_found_title'),
                __('errors/strings.notifications.not_found_body'),
            ];
        }

        if ($e instanceof AuthorizationException) {
            return [
                __('errors/strings.notifications.forbidden_title'),
                __('errors/strings.notifications.forbidden_body'),
            ];
        }

        if ($e instanceof TokenMismatchException) {
            return [
                __('errors/strings.notifications.session_expired_title'),
                __('errors/strings.notifications.session_expired_body'),
            ];
        }

        if ($e instanceof PostTooLargeException) {
            return [
                __('errors/strings.notifications.payload_too_large_title'),
                __('errors/strings.notifications.payload_too_large_body'),
            ];
        }

        return [
            __('errors/strings.notifications.generic_title'),
            __('errors/strings.notifications.generic_body'),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected static function classifyQueryException(QueryException $e): array
    {
        // errorInfo = [SQLSTATE, driver-specific code, driver-specific message]
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return match ($driverCode) {
            1264 => [ // Numeric value out of range
                __('errors/strings.notifications.number_too_large_title'),
                __('errors/strings.notifications.number_too_large_body'),
            ],
            1406 => [ // Data too long for column
                __('errors/strings.notifications.value_too_long_title'),
                __('errors/strings.notifications.value_too_long_body'),
            ],
            1265, 1366 => [ // Data truncated / incorrect value for type
                __('errors/strings.notifications.invalid_format_title'),
                __('errors/strings.notifications.invalid_format_body'),
            ],
            1048 => [ // Column cannot be null
                __('errors/strings.notifications.missing_required_title'),
                __('errors/strings.notifications.missing_required_body'),
            ],
            1062 => [ // Duplicate entry
                __('errors/strings.notifications.duplicate_title'),
                __('errors/strings.notifications.duplicate_body'),
            ],
            1451 => [ // Cannot delete/update parent row: a foreign key constraint fails
                __('errors/strings.notifications.fk_in_use_title'),
                __('errors/strings.notifications.fk_in_use_body'),
            ],
            1452 => [ // Cannot add/update child row: a foreign key constraint fails
                __('errors/strings.notifications.fk_invalid_reference_title'),
                __('errors/strings.notifications.fk_invalid_reference_body'),
            ],
            1213 => [ // Deadlock found
                __('errors/strings.notifications.busy_retry_title'),
                __('errors/strings.notifications.busy_retry_body'),
            ],
            1205 => [ // Lock wait timeout exceeded
                __('errors/strings.notifications.busy_retry_title'),
                __('errors/strings.notifications.busy_retry_body'),
            ],
            2002, 2006, 2013 => [ // Can't connect / server has gone away / lost connection
                __('errors/strings.notifications.connection_lost_title'),
                __('errors/strings.notifications.connection_lost_body'),
            ],
            1146, 1054, 1049 => [ // Table/column/database missing — a config or migration bug, not the user's fault
                __('errors/strings.notifications.system_config_title'),
                __('errors/strings.notifications.system_config_body'),
            ],
            default => [
                __('errors/strings.notifications.database_title'),
                __('errors/strings.notifications.database_body'),
            ],
        };
    }
}
