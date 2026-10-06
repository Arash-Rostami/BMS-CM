<?php

namespace Tests\Feature\Traits;

use App\Filament\Traits\HandlesActionExceptions;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * App\Filament\Traits\HandlesActionExceptions overrides callMountedAction() to catch
 * any Throwable (except ValidationException, which must reach Filament's own inline
 * field-error UI untouched) and present it as a persistent danger notification,
 * returning null instead of letting it bubble to Livewire's generic error fallback.
 * Composed on ListRecords/CreateRecord/EditRecord/ManageRecords + all 25
 * RelationManagers (filamentPattern.md §1.19) — genuinely app-wide, no existing
 * direct test of the trait's own catch/rethrow logic. No DB touch needed: the
 * trait's real `parent::callMountedAction()` call is exercised against a minimal
 * base class standing in for Filament's InteractsWithActions.
 */
class HandlesActionExceptionsTest extends TestCase
{
    private function host(callable $behavior): ActionExceptionHost
    {
        return new ActionExceptionHost($behavior);
    }

    public function test_a_successful_action_passes_its_return_value_through_untouched(): void
    {
        $this->assertSame('ok', $this->host(fn () => 'ok')->callMountedAction());
    }

    public function test_a_validation_exception_is_rethrown_not_swallowed(): void
    {
        $host = $this->host(function () {
            throw ValidationException::withMessages(['field' => 'required']);
        });

        $this->expectException(ValidationException::class);
        $host->callMountedAction();
    }

    public function test_any_other_throwable_is_caught_and_returns_null(): void
    {
        $host = $this->host(function () {
            throw new RuntimeException('boom');
        });

        $this->assertNull($host->callMountedAction());
    }

    public function test_the_caught_throwable_is_presented_as_a_persistent_danger_notification(): void
    {
        session()->forget('filament.notifications');

        $host = $this->host(function () {
            throw new RuntimeException('boom');
        });
        $host->callMountedAction();

        $notifications = collect(session('filament.notifications'));
        $this->assertNotEmpty($notifications);
        $this->assertSame('danger', $notifications->last()['status']);
    }
}

class ActionExceptionHostParent
{
    private $behavior;

    public function __construct(callable $behavior)
    {
        $this->behavior = $behavior;
    }

    public function callMountedAction(array $arguments = []): mixed
    {
        return ($this->behavior)($arguments);
    }
}

class ActionExceptionHost extends ActionExceptionHostParent
{
    use HandlesActionExceptions;
}
