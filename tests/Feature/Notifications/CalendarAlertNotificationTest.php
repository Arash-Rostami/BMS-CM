<?php

namespace Tests\Feature\Notifications;

use App\Livewire\CalendarDatabaseNotifications;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use App\Services\Calendar\CalendarSeen;
use App\Services\Calendar\Sync\CalendarRouter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAlertNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
        Carbon::setTestNow(Carbon::parse('2026-10-08'));
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        CalendarRouter::flushRoutes();
        Queue::fake(); // observers are live: never let a dispatch run inline under QUEUE_CONNECTION=sync
    }

    protected function tearDown(): void
    {
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        CalendarRouter::flushRoutes();
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    public function test_the_bell_renders_a_stored_alert_in_the_viewer_locale(): void
    {
        $expectations = [
            'en' => 'Lead',
            'fa' => 'یادآوری',
            'fr' => 'Rappel',
        ];

        foreach ($expectations as $locale => $kind) {
            app()->setLocale($locale);
            $open = __('resources/calendarRule/strings.alerts.action_open');
            $seen = __('resources/calendarRule/strings.alerts.action_seen');
            $this->assertNotSame('resources/calendarRule/strings.alerts.action_open', $open);
            $user = User::factory()->create();
            $this->storedAlert($user);

            $this->actingAs($user);

            Livewire::test(CalendarDatabaseNotifications::class)
                ->assertSee('Quarterly review · 1')
                ->assertSee('PR-2601-004')
                ->assertSee($kind)
                ->assertSee($open)
                ->assertSee($seen);
        }
    }

    public function test_the_topbar_bell_trigger_and_unread_badge_render(): void
    {
        $user = User::factory()->create();
        $this->insertNotification($user, ['title' => 'Plain title', 'body' => 'Plain body', 'format' => 'filament']);

        $this->actingAs($user);

        Livewire::test(CalendarDatabaseNotifications::class)
            ->assertSeeHtml('fi-topbar-database-notifications-btn')
            ->assertSeeHtml('fi-icon-btn-badge-ctn');
    }

    public function test_the_bell_extends_the_panel_database_notifications_component(): void
    {
        $this->assertTrue(is_subclass_of(CalendarDatabaseNotifications::class, \Filament\Livewire\DatabaseNotifications::class));
        $this->assertSame(\Filament\Livewire\DatabaseNotifications::class, get_parent_class(CalendarDatabaseNotifications::class));
    }

    public function test_vendor_shaped_notifications_render_unchanged(): void
    {
        $user = User::factory()->create();
        $this->insertNotification($user, [
            'title' => 'Plain title',
            'body' => 'Plain body',
            'actions' => [['name' => 'view', 'label' => 'View Record', 'url' => '/dashboard', 'shouldMarkAsRead' => true]],
            'icon' => 'heroicon-o-bell',
            'iconColor' => 'info',
            'format' => 'filament',
            'duration' => 'persistent',
        ]);

        $this->actingAs($user);

        Livewire::test(CalendarDatabaseNotifications::class)
            ->assertSee('Plain title')
            ->assertSee('Plain body')
            ->assertSee('View Record');
    }

    public function test_a_malformed_stored_alert_renders_without_crashing(): void
    {
        $user = User::factory()->create();
        $this->insertNotification($user, [
            'title' => ['params' => 'zz'],
            'body' => 'x',
            'items' => 'nope',
            'actions' => 'nope',
            'format' => 'filament',
        ]);

        $this->actingAs($user);

        Livewire::test(CalendarDatabaseNotifications::class)->assertSuccessful();
    }

    public function test_a_translatable_alert_with_hostile_body_and_params_fails_closed_without_hiding_other_notifications(): void
    {
        $user = User::factory()->create();
        $this->insertNotification($user, [
            'title' => ['key' => 'resources/calendarRule/strings.alerts.title', 'params' => 'zz'],
            'body' => ['not', 'a', 'string'],
            'items' => [['label' => ['x'], 'kind' => 'lead'], 'junk'],
            'actions' => [['name' => 'seen', 'key' => 'resources/calendarRule/strings.alerts.action_seen']],
            'format' => 'filament',
        ]);
        $this->insertNotification($user, ['title' => 'Neighbour title', 'body' => 'Neighbour body', 'format' => 'filament']);

        $this->actingAs($user);

        Livewire::test(CalendarDatabaseNotifications::class)
            ->assertSuccessful()
            ->assertSee('Neighbour title')
            ->assertSee('Neighbour body');
    }

    public function test_the_mail_is_fully_translated_per_locale_and_links_with_the_app_url(): void
    {
        config(['app.url' => 'https://bms.example']);
        $user = User::factory()->create(['name' => 'Sara']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'Quarterly review', 'notification_type' => 'email']);
        $entries = collect(range(1, 5))->map(fn (int $i): array => [
            'hit' => CalendarHit::factory()->forSubject(PurchaseRequest::factory()->create())
                ->create(['calendar_rule_id' => $rule->id, 'label' => "PR-{$i}", 'event_date' => '2026-10-11']),
            'leads' => [3], 'on_day' => false, 'overdue' => false,
        ]);

        $subjects = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $mail = (new CalendarAlertNotification($rule, $entries, '2026-10-08'))->toMail($user);
            $text = implode('
', [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines, $mail->actionText]);

            $this->assertStringNotContainsString('resources/calendarRule', $text);
            $this->assertStringNotContainsString('strings.mail', $text);
            $this->assertStringContainsString('PR-1', $text);
            $this->assertStringContainsString(__('resources/calendarRule/strings.mail.more', ['more' => 2]), $text);
            $this->assertStringNotContainsString('PR-4', $text);
            $this->assertStringStartsWith('https://bms.example/', $mail->actionUrl);
            $this->assertStringNotContainsString('localhost', $mail->actionUrl);
            $subjects[$locale] = $mail->subject;
        }

        $this->assertCount(3, array_unique($subjects));
    }

    public function test_the_mail_for_an_outside_address_has_no_name_no_account_link_and_only_the_mail_channel(): void
    {
        $user = User::factory()->create();
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'Quarterly review', 'notification_type' => 'all']);
        $entries = collect([[
            'hit' => CalendarHit::factory()->forSubject(PurchaseRequest::factory()->create())
                ->create(['calendar_rule_id' => $rule->id, 'label' => 'PR-1', 'event_date' => '2026-10-11']),
            'leads' => [3], 'on_day' => false, 'overdue' => false,
        ]]);
        $outsider = (new AnonymousNotifiable)->route('mail', 'out@example.com');
        $notification = new CalendarAlertNotification($rule, $entries, '2026-10-08');

        $this->assertSame(['mail'], $notification->via($outsider));

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $mail = $notification->toMail($outsider);
            $text = implode(' ', [$mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

            $this->assertSame(__('resources/calendarRule/strings.mail.greeting_anonymous'), $mail->greeting);
            $this->assertStringNotContainsString(':name', $text);
            $this->assertStringNotContainsString('strings.mail', $text);
            $this->assertStringContainsString(__('resources/calendarRule/strings.mail.outro_anonymous'), $text);
            $this->assertNull($mail->actionUrl);
        }
    }

    private function storedAlert(User $user): CalendarRule
    {
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'name' => 'Quarterly review']);
        $hit = CalendarHit::factory()
            ->forSubject(PurchaseRequest::factory()->create())
            ->create(['calendar_rule_id' => $rule->id, 'label' => 'PR-2601-004', 'event_date' => '2026-10-11']);

        $user->notify(new CalendarAlertNotification(
            $rule,
            collect([['hit' => $hit->fresh(), 'leads' => [3], 'on_day' => false, 'overdue' => false]]),
            '2026-10-08',
        ));

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertNotification(User $user, array $data): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ModelEventNotification',
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

    private function viewer(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'bell_test_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']));
        $user->assignRole($role);

        return $user;
    }

    private function seenHitFor(CalendarRule $rule): CalendarHit
    {
        return CalendarHit::factory()->forSubject(PurchaseRequest::factory()->create())->create(['calendar_rule_id' => $rule->id, 'event_date' => '2026-10-11']);
    }

    /**
     * @param  array<int, CalendarHit>  $hits
     */
    private function alertFor(User $user, CalendarRule $rule, array $hits): string
    {
        $before = $user->notifications()->pluck('id')->all();
        $user->notify(new CalendarAlertNotification(
            $rule,
            collect($hits)->map(fn (CalendarHit $hit): array => ['hit' => $hit, 'leads' => [3], 'on_day' => false, 'overdue' => false]),
            '2026-10-08',
        ));

        return $user->notifications()->whereNotIn('id', $before)->value('id');
    }

    private function isUnread(string $id): bool
    {
        return DB::table('notifications')->where('id', $id)->whereNull('read_at')->exists();
    }

    public function test_marking_the_bell_alert_as_seen_only_clears_the_bell_and_hides_no_item(): void
    {
        $user = $this->viewer();
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'visibility' => 'me']);
        CalendarHit::flushVisibleRuleIds();
        [$a, $b] = [$this->seenHitFor($rule), $this->seenHitFor($rule)];
        $id = $this->alertFor($user, $rule, [$a, $b]);
        $this->actingAs($user);

        Livewire::test(CalendarDatabaseNotifications::class)->call('markNotificationAsRead', $id)->assertSuccessful();

        $this->assertFalse($this->isUnread($id));
        CalendarSeen::flush();
        $this->assertSame([], app(CalendarSeen::class)->readIds($user->fresh()));
        $this->assertSame(0, DB::table('calendar_reads')->where('user_id', $user->id)->count());
    }

    public function test_hiding_cards_never_touches_a_bell_alert_and_unhiding_changes_nothing_either(): void
    {
        $user = $this->viewer();
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'visibility' => 'everyone']);
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        [$h1, $h2, $h3] = [$this->seenHitFor($rule), $this->seenHitFor($rule), $this->seenHitFor($rule)];
        $full = $this->alertFor($user, $rule, [$h1, $h2]);
        $partial = $this->alertFor($user, $rule, [$h1, $h3]);
        $teammate = $this->viewer();
        $theirs = $this->alertFor($teammate, $rule, [$h1, $h2]);

        app(CalendarSeen::class)->mark($user, [$h1, $h2]);

        $this->assertTrue($this->isUnread($full));
        $this->assertTrue($this->isUnread($partial));
        $this->assertTrue($this->isUnread($theirs));

        app(CalendarSeen::class)->unmark($user, [$h1, $h2]);
        app(CalendarSeen::class)->mark($user, [$h1, $h3]);

        $this->assertTrue($this->isUnread($full));
        $this->assertTrue($this->isUnread($partial));
    }

    public function test_hiding_cards_works_for_rules_that_have_no_notification_rows(): void
    {
        $user = $this->viewer();
        $rule = CalendarRule::factory()->create(['user_id' => $user->id, 'visibility' => 'me', 'notification_type' => CalendarRule::NOTIFICATION_CHANNEL_EMAIL]);
        CalendarHit::flushVisibleRuleIds();
        CalendarSeen::flush();
        $hit = $this->seenHitFor($rule);

        app(CalendarSeen::class)->mark($user, [$hit]);

        $this->assertSame([$hit->id], app(CalendarSeen::class)->readIds($user));
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $user->id)->count());
    }
}
