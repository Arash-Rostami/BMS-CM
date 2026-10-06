<?php

namespace Tests\Feature\Services;

use App\Models\NotificationSetting;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use App\Services\NotificationEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use ReflectionProperty;
use Tests\TestCase;

class NotificationEvaluatorTest extends TestCase
{
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    private function makeSetting(User $recipient, array $overrides = [], string $type = 'in_app'): NotificationSetting
    {
        return NotificationSetting::create([
            'settings' => array_merge([
                'is_active' => true,
                'actions' => ['create'],
                'tables' => ['purchase_requests'],
                'users' => [$recipient->id],
            ], $overrides),
            'notification_type' => $type,
            'user_id' => $this->actor->id,
        ]);
    }

    private function sentChanges(User $recipient, string $notification): array
    {
        $sent = Notification::sent($recipient, $notification);

        $reflection = new ReflectionProperty($sent[0], 'changes');
        $reflection->setAccessible(true);

        return $reflection->getValue($sent[0]);
    }

    public function test_create_event_notifies_the_recipients_of_a_matching_setting(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertSentTo($recipient, ModelEventNotification::class);
    }

    public function test_setting_for_another_action_is_skipped(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['actions' => ['update']]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertNothingSentTo($recipient);
    }

    public function test_setting_for_another_table_is_skipped(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['tables' => ['shipments']]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertNothingSentTo($recipient);
    }

    public function test_inactive_setting_is_skipped(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['is_active' => false]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertNothingSentTo($recipient);
    }

    public function test_setting_with_an_empty_is_active_flag_is_treated_as_active(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['is_active' => []]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertSentTo($recipient, ModelEventNotification::class);
    }

    public function test_setting_without_an_is_active_flag_never_fires(): void
    {
        $recipient = User::factory()->create();
        NotificationSetting::create([
            'settings' => [
                'actions' => ['create'],
                'tables' => ['purchase_requests'],
                'users' => [$recipient->id],
            ],
            'notification_type' => 'in_app',
            'user_id' => $this->actor->id,
        ]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertNothingSentTo($recipient);
    }

    public function test_update_with_a_column_filter_notifies_when_a_watched_column_changed(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['actions' => ['update'], 'columns' => ['status_id']]);

        $request = PurchaseRequest::factory()->create();
        $newStatusId = Status::factory()->create()->id;
        $request->status_id = $newStatusId;

        app(NotificationEvaluator::class)->evaluate($request, 'update', ['status_id' => $newStatusId]);

        Notification::assertSentTo($recipient, ModelEventNotification::class);
    }

    public function test_update_with_a_column_filter_is_skipped_when_no_watched_column_changed(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['actions' => ['update'], 'columns' => ['status_id', 'notes']]);

        $request = PurchaseRequest::factory()->create();
        $request->urgency_level = 'high';

        app(NotificationEvaluator::class)->evaluate($request, 'update', ['urgency_level' => 'high']);

        Notification::assertNothingSentTo($recipient);
    }

    public function test_update_with_a_value_filter_notifies_when_the_current_value_is_in_the_pool(): void
    {
        $recipient = User::factory()->create();
        $newStatus = Status::factory()->create();
        $this->makeSetting($recipient, [
            'actions' => ['update'],
            'columns' => ['status_id'],
            'values' => [$newStatus->id],
        ]);

        $request = PurchaseRequest::factory()->create();
        $request->status_id = $newStatus->id;

        app(NotificationEvaluator::class)->evaluate($request, 'update', ['status_id' => $newStatus->id]);

        Notification::assertSentTo($recipient, ModelEventNotification::class);
    }

    public function test_update_with_a_value_filter_is_skipped_when_the_current_value_is_outside_the_pool(): void
    {
        $recipient = User::factory()->create();
        $newStatus = Status::factory()->create();
        $this->makeSetting($recipient, [
            'actions' => ['update'],
            'columns' => ['status_id'],
            'values' => [-1],
        ]);

        $request = PurchaseRequest::factory()->create();
        $request->status_id = $newStatus->id;

        app(NotificationEvaluator::class)->evaluate($request, 'update', ['status_id' => $newStatus->id]);

        Notification::assertNothingSentTo($recipient);
    }

    public function test_update_notification_resolves_fk_columns_to_display_values(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['actions' => ['update']]);

        $request = PurchaseRequest::factory()->create();
        $originalName = Status::find($request->getOriginal('status_id'))->english_name;
        $newStatus = Status::factory()->create();
        $request->status_id = $newStatus->id;

        app(NotificationEvaluator::class)->evaluate($request, 'update', ['status_id' => $newStatus->id]);

        $changes = $this->sentChanges($recipient, ModelEventNotification::class);
        $this->assertSame($originalName, $changes['status_id']['old']);
        $this->assertSame($newStatus->english_name, $changes['status_id']['new']);
    }

    public function test_email_type_sends_only_the_email_notification(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, type: 'email');

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertSentTo($recipient, ModelEventEmail::class);
        Notification::assertNotSentTo($recipient, ModelEventNotification::class);
    }

    public function test_all_type_sends_both_notifications(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, type: 'all');

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertSentTo($recipient, ModelEventEmail::class);
        Notification::assertSentTo($recipient, ModelEventNotification::class);
    }

    public function test_no_notification_is_sent_when_the_recipient_list_is_empty(): void
    {
        $recipient = User::factory()->create();
        $this->makeSetting($recipient, ['users' => []]);

        app(NotificationEvaluator::class)->evaluate(PurchaseRequest::factory()->create(), 'create');

        Notification::assertNothingSentTo($recipient);
    }
}