<?php

namespace Tests\Feature\Notifications;

use App\Livewire\CalendarDatabaseNotifications;
use App\Models\NotificationSetting;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ModelEventNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en']);
        app()->setLocale('en');
    }

    private function rendered(array $text): string
    {
        return __($text['key'], $text['params']);
    }

    private function makeSetting(array $attributes = []): NotificationSetting
    {
        return new NotificationSetting(array_merge([
            'settings' => [
                'is_active' => true,
                'actions' => ['create'],
                'tables' => ['purchase_requests'],
                'users' => [1],
            ],
            'notification_type' => 'in_app',
        ], $attributes));
    }

    private function makeRequest(array $attributes = []): PurchaseRequest
    {
        return (new PurchaseRequest(array_merge(['pr_number' => 'PR-2601-001'], $attributes)))
            ->forceFill(['id' => 5]);
    }

    public function test_identifier_uses_the_scannable_identifier_and_appends_the_contract_no(): void
    {
        $order = new RegisteredOrder(['ro_number' => 'RO-2601-001', 'contract_no' => 'CT-778899']);
        $database = (new ModelEventNotification($order, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Registered Order RO-2601-001 (CT-778899) has been created', $this->rendered($database['body']), 'The identifier shows the SCANNABLE_IDENTIFIER value plus the contract number in parentheses.');

        $database = (new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 has been created', $this->rendered($database['body']), 'Without a contract number the identifier is just the SCANNABLE_IDENTIFIER value.');
    }

    public function test_identifier_falls_back_to_the_record_key_when_the_scannable_identifier_is_missing(): void
    {
        $request = (new PurchaseRequest)->forceFill(['id' => 5, 'pr_number' => null]);
        $database = (new ModelEventNotification($request, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Purchase Request #5 has been created', $this->rendered($database['body']), 'An empty SCANNABLE_IDENTIFIER value falls back to the record key.');

        $status = (new Status)->forceFill(['id' => 9]);
        $database = (new ModelEventNotification($status, 'create', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('Status #9 has been created', $this->rendered($database['body']), 'A model without a SCANNABLE_IDENTIFIER constant goes straight to the record key.');
    }

    public function test_update_body_lists_the_headline_cased_changed_columns(): void
    {
        $changes = [
            'urgency_level' => ['old' => 'low', 'new' => 'high'],
            'required_by_date' => ['old' => '2026-01-01', 'new' => '2026-02-01'],
        ];

        $notification = new ModelEventNotification($this->makeRequest(), 'update', $changes, $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 updated: Urgency Level, Required By Date', $this->rendered($database['body']), 'The update body joins the headline-cased column names of every change.');

        $notification = new ModelEventNotification($this->makeRequest(), 'update', [], $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame('Purchase Request PR-2601-001 has changed', $this->rendered($database['body']), 'An update with no changes falls through to the generic body.');
    }

    public function test_each_action_arms_its_own_body_title_icon_and_color(): void
    {
        $expectations = [
            'create' => ['has been created', 'Purchase Request Created', 'heroicon-o-plus-circle', 'success'],
            'update' => ['has changed', 'Purchase Request Updated', 'heroicon-o-pencil-square', 'info'],
            'delete' => ['has been deleted', 'Purchase Request Deleted', 'heroicon-o-trash', 'danger'],
            'restore' => ['has changed', 'Purchase Request Changed', 'heroicon-o-bell', 'gray'],
        ];

        foreach ($expectations as $action => [$bodyFragment, $title, $icon, $color]) {
            $notification = new ModelEventNotification($this->makeRequest(), $action, [], $this->makeSetting());
            $database = $notification->toDatabase(new User);

            $this->assertSame($title, $this->rendered($database['title']), "The {$action} action should map to the '{$title}' title.");
            $this->assertStringContainsString($bodyFragment, $this->rendered($database['body']), "The {$action} action should say '{$bodyFragment}'.");
            $this->assertSame($icon, $database['icon'], "The {$action} action should map to the '{$icon}' icon.");
            $this->assertSame($color, $database['iconColor'], "The {$action} action should map to the '{$color}' color.");
        }
    }

    public function test_to_database_returns_the_filament_action_contract(): void
    {
        $notification = new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting());
        $database = $notification->toDatabase(new User);

        $this->assertSame([
            'name' => 'view',
            'key' => 'resources/notificationSetting/strings.notification.action_view',
            'url' => '/dashboard/purchase-requests/5/edit',
            'shouldMarkAsRead' => true,
        ], $database['actions'][0], 'The database notification carries a single edit action for the record.');
        $this->assertSame('filament', $database['format']);
        $this->assertSame('persistent', $database['duration']);
    }

    public function test_via_returns_the_channel_of_each_concrete_class(): void
    {
        $notification = new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting());
        $this->assertSame(['database'], $notification->via(new User), 'The in-app notification travels only over the database channel.');

        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting());
        $this->assertSame(['mail'], $email->via(new User), 'The email notification travels only over the mail channel.');
    }

    public function test_to_mail_renders_the_create_contract(): void
    {
        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting());
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertSame('Purchase Request Created: PR-2601-001', $mail->subject, 'The subject names the model and its identifier.');
        $this->assertSame('Hello Arash,', $mail->greeting, 'The greeting addresses the notifiable by name.');
        $this->assertContains('A new Purchase Request **PR-2601-001** has been created.🟢', $mail->introLines);
        $this->assertSame('View Record', $mail->actionText);
        $this->assertSame(rtrim(config('app.url'), '/').'/dashboard/purchase-requests/5/edit', $mail->actionUrl, 'The action button links to the record edit page on the app URL.');
        $this->assertContains('This notification was sent based on your notification settings.', $mail->outroLines);
        $this->assertNotContains('**Additional Notes:**', $mail->introLines, 'No notes block is rendered when the setting has no notes.');
    }

    public function test_to_mail_renders_update_changes_with_formatted_values(): void
    {
        $changes = [
            'urgency_level' => ['old' => null, 'new' => 'high'],
            'is_urgent' => ['old' => false, 'new' => true],
            'approval_date' => ['old' => new DateTime('2026-01-02 03:04:00'), 'new' => new DateTime('2026-02-03 04:05:00')],
            'notes' => ['old' => 'draft', 'new' => 'final'],
        ];

        $email = new ModelEventEmail($this->makeRequest(), 'update', $changes, $this->makeSetting());
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertSame('Purchase Request Updated: PR-2601-001', $mail->subject);
        $this->assertContains('The Purchase Request **PR-2601-001** has been updated.🟡', $mail->introLines);
        $this->assertContains('**Changes:**', $mail->introLines);
        $this->assertContains('• **Urgency Level:** (empty) → high', $mail->introLines, 'A null change value renders as (empty).');
        $this->assertContains('• **Is Urgent:** No → Yes', $mail->introLines, 'A boolean change value renders as No/Yes.');
        $this->assertContains('• **Approval Date:** '.toGregorianDate('2026-01-02 03:04:00', true).' → '.toGregorianDate('2026-02-03 04:05:00', true), $mail->introLines, 'en renders Gregorian.');

        app()->setLocale('fa');
        $fa = (new ModelEventEmail($this->makeRequest(), 'update', ['required_by_date' => ['old' => '2026-01-02', 'new' => '2026-02-03']], $this->makeSetting()))->toMail(new User(['name' => 'Arash']));
        $this->assertContains('• **'.NotificationSetting::columnLabel(['purchase_requests'], 'required_by_date').':** '.toPersianDate('2026-01-02').' → '.toPersianDate('2026-02-03'), $fa->introLines, 'fa renders the localized label and Jalali dates regardless of session.');
        $this->assertContains('• **Notes:** draft → final', $mail->introLines);
    }

    public function test_to_mail_renders_the_setting_notes_when_present(): void
    {
        $setting = $this->makeSetting(['notes' => 'Watch this one closely']);
        $email = new ModelEventEmail($this->makeRequest(), 'create', [], $setting);
        $mail = $email->toMail(new User(['name' => 'Arash']));

        $this->assertContains('**Additional Notes:**', $mail->introLines);
        $this->assertContains('Watch this one closely', $mail->introLines);
    }

    public function test_notifications_are_queued_after_commit_and_survive_a_missing_model(): void
    {
        foreach ([new ModelEventNotification($this->makeRequest(), 'create', [], $this->makeSetting()), new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting())] as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertTrue($notification->afterCommit);
            $this->assertTrue($notification->deleteWhenMissingModels);
        }
    }

    public function test_delete_links_to_the_list_page_and_other_actions_to_the_record(): void
    {
        $delete = (new ModelEventNotification($this->makeRequest(), 'delete', [], $this->makeSetting()))->toDatabase(new User);
        $update = (new ModelEventNotification($this->makeRequest(), 'update', [], $this->makeSetting()))->toDatabase(new User);

        $this->assertSame('/dashboard/purchase-requests', $delete['actions'][0]['url'], 'A deleted record has no edit page, so the link goes to the list.');
        $this->assertSame('/dashboard/purchase-requests/5/edit', $update['actions'][0]['url'], 'Positive control: an update still links to the record.');
    }

    public function test_mail_link_has_no_double_slash_for_either_app_url_shape(): void
    {
        foreach (['http://example.test', 'http://example.test/'] as $url) {
            config(['app.url' => $url]);
            $mail = (new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting()))->toMail(new User(['name' => 'Arash']));

            $this->assertSame('http://example.test/dashboard/purchase-requests/5/edit', $mail->actionUrl);
        }

        $delete = (new ModelEventEmail($this->makeRequest(), 'delete', [], $this->makeSetting()))->toMail(new User(['name' => 'Arash']));
        $this->assertSame('http://example.test/dashboard/purchase-requests', $delete->actionUrl);
    }

    public function test_mail_renders_in_the_locale_of_the_sending_process(): void
    {
        foreach (['fr' => 'Bonjour Arash,', 'en' => 'Hello Arash,'] as $locale => $greeting) {
            app()->setLocale($locale);
            $mail = (new ModelEventEmail($this->makeRequest(), 'create', [], $this->makeSetting()))->toMail(new User(['name' => 'Arash']));

            $this->assertSame($greeting, $mail->greeting);
        }
    }

    public function test_every_notification_key_exists_in_all_three_locales(): void
    {
        $payload = (new ModelEventNotification($this->makeRequest(), 'update', ['notes' => ['old' => 'a', 'new' => 'b']], $this->makeSetting()))->toDatabase(new User);

        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach (['create', 'update', 'delete', 'default'] as $suffix) {
                foreach (['title.', 'body.', 'mail.subject.', 'mail.intro.'] as $group) {
                    $key = 'resources/notificationSetting/strings.notification.'.$group.$suffix;
                    $this->assertTrue(trans()->hasForLocale($key, $locale), "{$key} missing in {$locale}");
                }
            }
            $this->assertTrue(trans()->hasForLocale($payload['actions'][0]['key'], $locale));
        }
    }

    public function test_the_bell_renders_a_stored_event_in_the_viewer_locale(): void
    {
        $this->useMysql();
        DB::beginTransaction();

        try {
            $user = User::factory()->create();
            $payload = (new ModelEventNotification($this->makeRequest(), 'update', ['notes' => ['old' => 'a', 'new' => 'b']], $this->makeSetting()))->toDatabase($user);
            $this->storeNotification($user, $payload);
            $this->actingAs($user);

            $expectations = ['en' => ['Purchase Request Updated', 'View Record'], 'fa' => ['به‌روزرسانی شد', 'مشاهده رکورد'], 'fr' => ['mis à jour', 'Voir l’enregistrement']];

            foreach ($expectations as $locale => [$title, $action]) {
                app()->setLocale($locale);

                Livewire::test(CalendarDatabaseNotifications::class)
                    ->assertSee($title)
                    ->assertSee($action)
                    ->assertSee('PR-2601-001');
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_a_hostile_event_payload_fails_closed_in_the_bell(): void
    {
        $this->useMysql();
        DB::beginTransaction();

        try {
            $user = User::factory()->create();
            $this->storeNotification($user, [
                'title' => ['key' => 'resources/notificationSetting/strings.notification.title.create', 'params' => ['model' => ['x'], 'module' => ['y'], 'identifier' => 'ok']],
                'body' => ['key' => 'resources/notificationSetting/strings.notification.body.update', 'params' => 'zz'],
                'actions' => [['name' => 'view', 'key' => ['bad']], 'junk'],
                'format' => 'filament',
            ]);
            $this->storeNotification($user, ['title' => 'Neighbour', 'body' => 'Still here', 'format' => 'filament']);
            $this->actingAs($user);

            Livewire::test(CalendarDatabaseNotifications::class)
                ->assertSuccessful()
                ->assertSee('Neighbour')
                ->assertSee('Still here');
        } finally {
            DB::rollBack();
        }
    }

    private function storeNotification(User $user, array $data): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => ModelEventNotification::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
}
