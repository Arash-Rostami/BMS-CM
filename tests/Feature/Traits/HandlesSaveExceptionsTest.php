<?php

namespace Tests\Feature\Traits;

use App\Filament\Traits\HandlesSaveExceptions;
use Filament\Support\Exceptions\Halt;
use RuntimeException;
use Tests\TestCase;

/**
 * App\Filament\Traits\HandlesSaveExceptions::reportSaveException() presents the
 * exception as a persistent danger notification, then throws Halt with
 * rollBackDatabaseTransaction() explicitly set true — Halt defaults that flag to
 * false (commit), so the explicit call is load-bearing (filamentPattern.md §1.19).
 * Composed on CreateRecord/EditRecord, the base classes every one of this project's
 * Create/Edit pages extends — cross-cutting in effect, no existing direct test.
 */
class HandlesSaveExceptionsTest extends TestCase
{
    public function test_report_save_exception_always_throws_halt(): void
    {
        $host = new SaveExceptionHost;

        $this->expectException(Halt::class);
        $host->report(new RuntimeException('boom'));
    }

    public function test_the_thrown_halt_requests_a_transaction_rollback(): void
    {
        $host = new SaveExceptionHost;

        try {
            $host->report(new RuntimeException('boom'));
            $this->fail('Expected a Halt to be thrown.');
        } catch (Halt $halt) {
            $this->assertTrue($halt->shouldRollbackDatabaseTransaction());
        }
    }

    public function test_the_exception_is_presented_as_a_persistent_danger_notification_before_the_halt(): void
    {
        session()->forget('filament.notifications');
        $host = new SaveExceptionHost;

        try {
            $host->report(new RuntimeException('boom'));
        } catch (Halt) {
        }

        $notifications = collect(session('filament.notifications'));
        $this->assertNotEmpty($notifications);
        $this->assertSame('danger', $notifications->last()['status']);
    }
}

class SaveExceptionHost
{
    use HandlesSaveExceptions;

    public function report(\Throwable $e): never
    {
        $this->reportSaveException($e);
    }
}
