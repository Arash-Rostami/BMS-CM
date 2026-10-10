<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CalendarRuleResource;
use App\Filament\Resources\Master\CalendarRuleResource\Pages\ManageCalendarRules;
use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\NotificationSettingResource;
use App\Livewire\CalendarDatabaseNotifications;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AlertsHubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Queue::fake();
        app()->setLocale('en');
        Carbon::setTestNow(Carbon::parse('2026-10-08'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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

    private function actingAsUserWith(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'alerts_hub_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function alertsGroup(): ?array
    {
        app()->forgetInstance(NavigationManager::class);

        $group = collect(Filament::getNavigation())
            ->first(fn ($group): bool => $group->getLabel() === __('resources/dashboard/strings.navigation_group.alerts'));

        return $group === null ? null : collect($group->getItems())->map->getLabel()->all();
    }

    private function switcher(string $page): array
    {
        $html = Livewire::test($page)->assertSuccessful()->html();
        preg_match_all('/<a\b[^>]*class="([^"]*fi-tabs-item[^"]*)"[^>]*>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m): array => [
            trim(preg_replace('/\s+/', ' ', strip_tags($m[2]))) => str_contains($m[1], 'fi-active'),
        ])->all();
    }

    public function test_the_alerts_group_holds_both_resources_in_order(): void
    {
        $this->actingAsUserWith(['calendar_rule.view']);

        $this->assertSame([
            NotificationSettingResource::getNavigationLabel(),
            CalendarRuleResource::getNavigationLabel(),
        ], $this->alertsGroup());
    }

    public function test_a_user_without_calendar_permissions_sees_only_the_notification_item_in_the_group(): void
    {
        $this->actingAsUserWith(['purchase_request.view']);

        $this->assertSame([NotificationSettingResource::getNavigationLabel()], $this->alertsGroup());
    }

    public function test_the_alert_navigation_items_carry_no_count_badge(): void
    {
        $user = $this->actingAsUserWith(['calendar_rule.view', 'notification_setting.view']);
        CalendarRule::factory()->create(['user_id' => $user->id]);

        $this->assertNull(CalendarRuleResource::getNavigationBadge());
        $this->assertNull(CalendarRuleResource::getNavigationBadgeColor());
        $this->assertNull(NotificationSettingResource::getNavigationBadge());
        $this->assertNull(NotificationSettingResource::getNavigationBadgeColor());
    }

    public function test_the_old_nested_parent_no_longer_exists(): void
    {
        foreach ([NotificationSettingResource::class, CalendarRuleResource::class] as $resource) {
            $this->assertNotSame(
                $resource,
                (new \ReflectionMethod($resource, 'getNavigationParentItem'))->class
            );
        }

        $this->actingAsUserWith(['calendar_rule.view']);
        app()->forgetInstance(NavigationManager::class);
        $base = collect(Filament::getNavigation())
            ->first(fn ($group): bool => $group->getLabel() === __('resources/dashboard/strings.navigation_group.base'));

        $this->assertNotContains(
            __('resources/dashboard/strings.navigation_group.alerts'),
            collect($base?->getItems())->map->getLabel()->all()
        );
    }

    public function test_the_switcher_shows_both_items_with_the_current_page_active(): void
    {
        $this->actingAsUserWith(['calendar_rule.view']);
        $notifications = __('resources/general/strings.alerts_switcher.notifications');
        $calendar = __('resources/general/strings.alerts_switcher.calendar');

        $this->assertSame([$notifications => true, $calendar => false], $this->switcher(ManageNotificationSettings::class));
        $this->assertSame([$notifications => false, $calendar => true], $this->switcher(ManageCalendarRules::class));
    }

    public function test_the_switcher_hides_the_calendar_item_without_calendar_view(): void
    {
        $this->actingAsUserWith(['purchase_request.view']);

        $this->assertSame(
            [__('resources/general/strings.alerts_switcher.notifications') => true],
            $this->switcher(ManageNotificationSettings::class)
        );
    }

    public function test_the_group_and_switcher_labels_are_translated_per_locale(): void
    {
        $this->actingAsUserWith(['calendar_rule.view']);

        $expected = [
            'fa' => ['【!】 مدیریت لاگ و هشدارها ⥃ ', 'وقتی اتفاقی می‌افتد', 'وقتی تاریخی نزدیک می‌شود'],
            'fr' => ['【!】 Journaux & Alertes', 'Quand un événement se produit', 'Quand une date approche'],
        ];

        foreach ($expected as $locale => [$group, $notifications, $calendar]) {
            app()->setLocale($locale);

            $this->assertSame($group, __('resources/dashboard/strings.navigation_group.alerts'));
            $this->assertCount(2, $this->alertsGroup());
            $this->assertSame([$notifications => true, $calendar => false], $this->switcher(ManageNotificationSettings::class));
        }

        app()->setLocale('en');
    }

    public function test_the_topbar_quick_create_still_lists_the_calendar_entry(): void
    {
        $this->actingAsUserWith(['calendar_rule.view', 'calendar_rule.create']);

        $menu = view('filament.partials.work-actions')->render();

        $this->assertStringContainsString('/dashboard/calendar-rules?action=create', $menu);
        $this->assertStringContainsString(CalendarRuleResource::getNavigationLabel(), $menu);
    }

    public function test_the_calendar_alert_notification_carries_the_calendar_icon_to_the_bell(): void
    {
        $user = $this->actingAsUserWith(['calendar_rule.view', 'purchase_request.view']);
        $rule = CalendarRule::factory()->create(['user_id' => $user->id]);
        $hit = CalendarHit::factory()
            ->forSubject(PurchaseRequest::factory()->create())
            ->create(['calendar_rule_id' => $rule->id, 'label' => 'PR-2601-004', 'event_date' => '2026-10-11']);

        $user->notify(new CalendarAlertNotification(
            $rule,
            collect([['hit' => $hit->fresh(), 'leads' => [3], 'on_day' => false, 'overdue' => false]]),
            '2026-10-08',
        ));

        $stored = $user->notifications()->firstOrFail();
        $rendered = Livewire::test(CalendarDatabaseNotifications::class)->instance()->getNotification($stored);

        $this->assertSame('heroicon-o-calendar-days', $stored->data['icon']);
        $this->assertSame('heroicon-o-calendar-days', $rendered->getIcon());
        $this->assertSame('info', $rendered->getIconColor());

        foreach (['fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $localized = Livewire::test(CalendarDatabaseNotifications::class)->instance()->getNotification($stored);

            $this->assertSame('heroicon-o-calendar-days', $localized->getIcon());
            $this->assertSame('info', $localized->getIconColor());
        }

        app()->setLocale('en');
    }
}
