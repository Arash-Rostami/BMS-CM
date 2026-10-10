<?php

namespace Tests\Feature\Traits;

use App\Filament\Resources\CalendarRuleResource;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Master\CalendarRuleResource\Pages\ManageCalendarRules;
use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\NotificationSettingResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Traits\HasDeskReferenceAction;
use App\Filament\Traits\HasDeskReferenceTab;
use App\Models\Bank;
use App\Models\DeskReference;
use App\Models\PurchaseRequest;
use App\Models\User;
use Filament\Schemas\Components\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * App\Filament\Traits\HasDeskReferenceAction::getDeskReferenceHeaderAction() resolves
 * a config+lang-driven header Action, or null when the resource isn't registered in
 * config/desk-reference.php, and tracks read/unread per (group_key, version) — NOT
 * per resource, so acknowledging one sibling module clears the reminder on every
 * other resource sharing that group (filamentPattern.md §1.27). Composed on 7
 * unrelated resources (BankProfile, Custom, Payment, RegisteredOrder, PurchaseRequest,
 * PurchaseOrder, Shipment) — cross-cutting, no existing coverage.
 *
 * The HasDeskReferenceTab::getDeskReferenceInfolistTab() tests below pin the
 * infolist-tab sibling: config+lang driven, version-pinned "●" dot badge, null
 * without a config entry or without reference content. The trait is currently
 * composed on no resource, so its contract runs through the probe class.
 */
class HasDeskReferenceActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
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

    public function test_purchase_request_resource_composes_the_trait(): void
    {
        $this->assertContains(HasDeskReferenceAction::class, class_uses_recursive(PurchaseRequestResource::class));
    }

    public function test_returns_null_for_a_resource_not_registered_in_config(): void
    {
        $this->assertNull(HasDeskReferenceActionBankProbe::getDeskReferenceHeaderAction());
    }

    public function test_returns_an_action_for_a_registered_resource_with_real_lang_content(): void
    {
        $action = PurchaseRequestResource::getDeskReferenceHeaderAction();

        $this->assertNotNull($action);
        $this->assertSame('deskReference', $action->getName());
    }

    public function test_the_action_is_warning_colored_and_unread_until_acknowledged(): void
    {
        $this->actingAs(User::factory()->create());

        $action = PurchaseRequestResource::getDeskReferenceHeaderAction();

        $this->assertSame('warning', $action->getColor());
    }

    public function test_the_action_turns_gray_once_the_user_has_acknowledged_that_version(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        DeskReference::factory()->create([
            'user_id' => $user->id,
            'group_key' => 'request_approval',
            'version' => config('desk-reference.purchaseRequest.version'),
        ]);

        $action = PurchaseRequestResource::getDeskReferenceHeaderAction();

        $this->assertSame('gray', $action->getColor());
    }

    public function test_acknowledging_one_sibling_module_clears_the_reminder_on_another_sharing_the_same_group(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame('logistics', config('desk-reference.shipment.group'));
        $this->assertSame('logistics', config('desk-reference.custom.group'));

        DeskReference::factory()->create([
            'user_id' => $user->id,
            'group_key' => 'logistics',
            'version' => config('desk-reference.shipment.version'),
        ]);

        $this->assertSame('gray', ShipmentResource::getDeskReferenceHeaderAction()->getColor());
        $this->assertSame('gray', CustomResource::getDeskReferenceHeaderAction()->getColor());
    }

    public static function alertModuleProvider(): array
    {
        return [
            'notification settings' => [NotificationSettingResource::class, ManageNotificationSettings::class, 'notificationSetting', 'notification_settings'],
            'calendar rules' => [CalendarRuleResource::class, ManageCalendarRules::class, 'calendarRule', 'calendar_rules'],
        ];
    }

    #[DataProvider('alertModuleProvider')]
    public function test_alert_module_entries_resolve_to_an_unread_action(string $resource, string $page, string $key, string $group): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertContains(HasDeskReferenceAction::class, class_uses_recursive($resource));
        $this->assertSame($group, config("desk-reference.{$key}.group"));

        $action = $resource::getDeskReferenceHeaderAction();

        $this->assertNotNull($action);
        $this->assertSame('warning', $action->getColor());
    }

    #[DataProvider('alertModuleProvider')]
    public function test_alert_module_action_sits_beside_create_on_the_manage_page(string $resource, string $page): void
    {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $names = array_map(fn ($action) => $action->getName(), $method->invoke(new $page));

        $this->assertSame(['deskReference', 'create'], $names);
    }

    #[DataProvider('alertModuleProvider')]
    public function test_alert_module_turns_gray_only_for_its_own_group_and_version(string $resource, string $page, string $key, string $group): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        DeskReference::factory()->create(['user_id' => $user->id, 'group_key' => $group, 'version' => config("desk-reference.{$key}.version") + 1]);
        $this->assertSame('warning', $resource::getDeskReferenceHeaderAction()->getColor());

        DeskReference::factory()->create(['user_id' => $user->id, 'group_key' => 'logistics', 'version' => 1]);
        $this->assertSame('warning', $resource::getDeskReferenceHeaderAction()->getColor());

        DeskReference::query()->where('user_id', $user->id)->where('group_key', $group)->delete();
        DeskReference::factory()->create(['user_id' => $user->id, 'group_key' => $group, 'version' => config("desk-reference.{$key}.version")]);
        $this->assertSame('gray', $resource::getDeskReferenceHeaderAction()->getColor());
    }

    #[DataProvider('alertModuleProvider')]
    public function test_alert_module_guides_have_every_section_filled_with_equal_shape_in_all_locales(string $resource, string $page, string $key, string $group): void
    {
        $shape = null;

        foreach (['en', 'fa', 'fr'] as $locale) {
            $content = require lang_path("{$locale}/deskReference/{$group}.php");

            foreach (['tab_label', 'tips', 'terms', 'process', 'dos', 'donts'] as $section) {
                $this->assertNotEmpty($content[$section] ?? null, "{$locale}/{$group} has an empty [{$section}].");
            }

            $counts = array_map(fn ($section) => count((array) $content[$section]), ['tips', 'terms', 'process', 'dos', 'donts']);
            $shape ??= $counts;
            $this->assertSame($shape, $counts, "{$locale}/{$group} differs in section sizes from en.");
        }
    }

    // HasDeskReferenceTab

    public function test_the_infolist_tab_returns_null_for_a_resource_not_registered_in_config(): void
    {
        HasDeskReferenceTabProbe::$model = Bank::class;

        $this->assertNull(HasDeskReferenceTabProbe::getDeskReferenceInfolistTab());
    }

    public function test_the_infolist_tab_is_an_unread_dot_badged_tab_for_an_unseen_user(): void
    {
        $this->actingAs(User::factory()->create());
        HasDeskReferenceTabProbe::$model = PurchaseRequest::class;

        $tab = HasDeskReferenceTabProbe::getDeskReferenceInfolistTab();

        $this->assertNotNull($tab, 'A registered resource with real lang content must resolve a desk-reference tab.');
        $this->assertSame('desk-reference', $tab->getKey(isAbsolute: false), 'The tab must carry the fixed desk-reference key.');
        $this->assertSame(config('desk-reference.purchaseRequest.icon'), $tab->getIcon());
        $this->assertSame('●', $tab->getBadge(), 'An unseen user must get the unread dot badge.');
        $this->assertSame('warning', $tab->getBadgeColor());

        $view = $tab->getDefaultChildComponents()[0] ?? null;
        $this->assertInstanceOf(View::class, $view, 'The tab body must be the desk-reference panel view.');

        $viewName = new ReflectionProperty(View::class, 'view');
        $viewName->setAccessible(true);

        $this->assertSame('filament.desk-reference.panel', $viewName->getValue($view));
        $this->assertSame([
            'group' => 'request_approval',
            'version' => config('desk-reference.purchaseRequest.version'),
            'currentModule' => 'purchaseRequest',
        ], Arr::only($view->getViewData(), ['group', 'version', 'currentModule']));
    }

    public function test_the_infolist_tab_clears_the_dot_badge_once_the_user_acknowledged_that_version(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        HasDeskReferenceTabProbe::$model = PurchaseRequest::class;

        DeskReference::factory()->create([
            'user_id' => $user->id,
            'group_key' => 'request_approval',
            'version' => config('desk-reference.purchaseRequest.version'),
        ]);

        $tab = HasDeskReferenceTabProbe::getDeskReferenceInfolistTab();

        $this->assertNotNull($tab);
        $this->assertNull($tab->getBadge(), 'An acknowledged version must clear the unread dot.');
    }

    public function test_acknowledging_a_different_version_keeps_the_unread_dot(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        HasDeskReferenceTabProbe::$model = PurchaseRequest::class;

        DeskReference::factory()->create([
            'user_id' => $user->id,
            'group_key' => 'request_approval',
            'version' => config('desk-reference.purchaseRequest.version') + 1,
        ]);

        $tab = HasDeskReferenceTabProbe::getDeskReferenceInfolistTab();

        $this->assertNotNull($tab);
        $this->assertSame('●', $tab->getBadge(), 'Only the exact current version counts as seen — an older or newer acknowledgment keeps the dot.');
    }

    public function test_the_infolist_tab_returns_null_when_the_group_lang_has_no_reference_content(): void
    {
        app('translator')->addLines(['deskReference/empty_group.tab_label' => 'Synthetic Empty Group'], 'en');
        config(['desk-reference.bank' => ['group' => 'empty_group', 'icon' => 'heroicon-o-book-open', 'version' => 1]]);
        HasDeskReferenceTabProbe::$model = Bank::class;

        $this->assertNull(HasDeskReferenceTabProbe::getDeskReferenceInfolistTab(), 'A group whose lang content has no terms, process, dos or donts must not produce a tab.');
    }
}

class HasDeskReferenceActionBankProbe
{
    use HasDeskReferenceAction;

    public static function getModel(): string
    {
        return Bank::class;
    }
}

class HasDeskReferenceTabProbe
{
    use HasDeskReferenceTab;

    public static string $model = Bank::class;

    public static function getModel(): string
    {
        return static::$model;
    }
}
