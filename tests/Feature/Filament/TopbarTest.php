<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\CorrespondenceResource\Pages\ListCorrespondences;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\CreatePurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Filament\Resources\Master\DepartmentResource\Pages\ManageDepartments;
use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Livewire\CalendarToggle;
use App\Livewire\RowClickToggle;
use App\Livewire\TableStateToggle;
use App\Models\Department;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class TopbarTest extends TestCase
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

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();

        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private array $operationalSlugs = [
        'purchase-requests',
        'proforma-invoices',
        'registered-orders',
        'bank-profiles',
        'correspondences',
        'purchase-orders',
        'payments',
        'shipments',
        'customs',
    ];

    public function test_work_actions_lists_only_permitted_create_targets(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.create']);

        $html = view('filament.partials.work-actions')->render();

        $this->assertStringContainsString('/dashboard/purchase-requests/create', $html);
        $this->assertStringNotContainsString('/dashboard/payments/create', $html);
        $this->assertStringNotContainsString('/dashboard/shipments/create', $html);
    }

    public function test_work_actions_hides_the_create_button_without_any_create_permissions(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = view('filament.partials.work-actions')->render();

        $this->assertStringNotContainsString(__('resources/general/strings.topbar.quick_create'), $html);
        foreach ($this->operationalSlugs as $slug) {
            $this->assertStringNotContainsString("/dashboard/{$slug}/create", $html);
        }
    }

    public function test_work_actions_create_groups_follow_pipeline_navigation_groups(): void
    {
        $this->actingAsUserWithPermissions([
            'purchase_request.view', 'proforma_invoice.view', 'registered_order.view',
            'bank_profile.view', 'correspondence.view', 'purchase_order.view',
            'payment.view', 'shipment.view', 'custom.view',
            'purchase_request.create', 'proforma_invoice.create', 'registered_order.create',
            'bank_profile.create', 'correspondence.create', 'purchase_order.create',
            'payment.create', 'shipment.create', 'custom.create',
        ]);

        $html = view('filament.partials.work-actions')->render();
        $menu = preg_replace('/<script>.*?<\/script>/s', '', $html);

        foreach ($this->operationalSlugs as $slug) {
            $this->assertStringContainsString("/dashboard/{$slug}/create", $menu);
        }

        $positions = [];
        foreach (['operational_first', 'operational_second', 'operational_third', 'operational_fourth'] as $group) {
            $label = __("resources/dashboard/strings.navigation_group.{$group}");
            $this->assertStringContainsString($label, $menu);
            $positions[$group] = strpos($menu, $label);
        }

        $this->assertLessThan($positions['operational_second'], $positions['operational_first']);
        $this->assertLessThan($positions['operational_third'], $positions['operational_second']);
        $this->assertLessThan($positions['operational_fourth'], $positions['operational_third']);
    }

    public function test_work_actions_exports_the_full_resource_map_for_recents(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $html = view('filament.partials.work-actions')->render();

        $this->assertStringContainsString('window.BMS_RESOURCE_MAP', $html);
        foreach ($this->operationalSlugs as $slug) {
            $this->assertStringContainsString("\"{$slug}\"", $html);
        }

        // the clear-history footer carries a trash icon
        $this->assertStringContainsString('h-3.5 w-3.5', $html);
        $this->assertStringContainsString('a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165', $html);
    }

    public function test_calendar_toggle_renders_its_abbr_and_switches_its_state(): void
    {
        session(['calendar_type' => 'gregorian']);

        Livewire::test(CalendarToggle::class)
            ->assertSee(__('resources/general/strings.calendar_toggle.gregorian_abbr'))
            ->call('toggle')
            ->assertSee(__('resources/general/strings.calendar_toggle.jalali_abbr'));
    }

    public function test_theme_partial_uses_the_icon_button_on_panel_and_lp_surface_elsewhere(): void
    {
        $panel = view('filament.partials.theme', ['surface' => 'panel'])->render();
        $lp = view('filament.partials.theme', ['surface' => 'lp'])->render();

        $this->assertStringContainsString('h-[22px] w-[22px]', $panel);
        $this->assertStringContainsString('setTheme(', $panel);
        // the order attr must land raw in the html — building it as
        // {{ $isPanel ? 'style="…"' : '' }} gets the quotes escaped to &quot; and silently kills the style
        $this->assertStringContainsString('style="order: 7"', $panel);
        $this->assertStringNotContainsString('&quot;order', $panel);

        $this->assertStringContainsString('lp-surface-hover', $lp);
        $this->assertStringContainsString(__('resources/general/strings.theme_palette.palettes.slate'), $lp);
    }

    public function test_language_switch_uses_text_mode_not_flags(): void
    {
        $switch = \BezhanSalleh\LanguageSwitch\LanguageSwitch::make();

        $this->assertFalse($switch->isFlagsOnly());
        $this->assertEmpty($switch->getFlags());
    }

    public function test_search_bell_divider_renders_between_search_and_bell(): void
    {
        $divider = view('filament.partials.topbar-divider')->render();

        // same hairline as the work-actions divider, visible on mobile too, ordered between the bell and search
        $this->assertStringContainsString('w-px self-stretch my-1.5 bg-black/10 dark:bg-white/10', $divider);
        $this->assertStringContainsString('style="order: 12"', $divider);
    }

    public function test_topbar_lang_keys_exist_in_all_three_locales(): void
    {
        $keys = ['quick_create', 'recent_records', 'recents_empty', 'recents_clear'];

        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach ($keys as $key) {
                $this->assertTrue(
                    trans()->has("resources/general/strings.topbar.{$key}", $locale),
                    "Missing topbar.{$key} in {$locale}"
                );
            }
        }
    }

    public function test_create_and_edit_pages_bind_mod_s_to_the_primary_save_action_only(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.create', 'purchase_request.edit']);
        $record = PurchaseRequest::factory()->create();

        $bindings = function (object $page, string $method): array {
            $reflection = new ReflectionMethod($page, $method);
            $reflection->setAccessible(true);

            return $reflection->invoke($page)->getKeyBindings();
        };

        $create = Livewire::test(CreatePurchaseRequest::class)->instance();
        $this->assertSame(['mod+s'], $bindings($create, 'getCreateFormAction'));
        // createAnother keeps Filament's own distinct vendor default — no mod+s double-fire
        $this->assertSame(['mod+shift+s'], $bindings($create, 'getCreateAnotherFormAction'));

        $edit = Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()])->instance();
        $this->assertSame(['mod+s'], $bindings($edit, 'getSaveFormAction'));
    }

    public function test_table_state_toggle_flips_the_persist_session_flag(): void
    {
        session()->forget('persist_table_state');

        $component = Livewire::test(TableStateToggle::class)
            ->assertSet('persisted', false)
            ->assertSee(__('resources/general/strings.table_state.enable'))
            ->call('toggle')
            ->assertSet('persisted', true)
            ->assertSee(__('resources/general/strings.table_state.disable'));

        $this->assertTrue(session('persist_table_state'));

        $component->call('toggle')->assertSet('persisted', false);
        $this->assertFalse(session('persist_table_state'));
    }

    public function test_table_search_persists_across_mounts_only_while_the_flag_is_on(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        session(['persist_table_state' => true]);
        Livewire::test(ListCorrespondences::class)
            ->set('tableSearch', 'zzz-unmatched')
            ->assertSuccessful();

        Livewire::test(ListCorrespondences::class)
            ->assertSet('tableSearch', 'zzz-unmatched');

        session()->forget('persist_table_state');
        Livewire::test(ListCorrespondences::class)
            ->assertSet('tableSearch', '');
    }

    public function test_table_state_lang_keys_exist_in_all_three_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach (['enable', 'disable'] as $key) {
                $this->assertTrue(
                    trans()->has("resources/general/strings.table_state.{$key}", $locale),
                    "Missing table_state.{$key} in {$locale}"
                );
            }
        }
    }

    public function test_row_click_toggle_flips_the_session_flag(): void
    {
        session()->forget('row_click_edit');

        $component = Livewire::test(RowClickToggle::class)
            ->assertSet('editOnClick', false)
            ->call('toggle')
            ->assertSet('editOnClick', true);

        $this->assertTrue(session('row_click_edit'));

        $component->call('toggle')->assertSet('editOnClick', false);
        $this->assertFalse(session('row_click_edit'));
    }

    public function test_row_click_wraps_more_cells_in_the_edit_link_when_toggled_on(): void
    {
        // The row's own "..." dropdown already contains one real <a href> to the edit page
        // (Operational EditAction navigates, it isn't a modal) — that link exists regardless
        // of this feature, so presence alone can't distinguish on/off. recordUrl() additionally
        // wraps every data cell in its own <a> to the same href, so the toggled-on render must
        // contain strictly MORE occurrences of that URL than the toggled-off render of the
        // exact same record.
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.edit']);
        $record = PurchaseOrder::factory()->create();
        $editUrl = route('filament.dashboard.resources.purchase-orders.edit', ['record' => $record]);

        session()->forget('row_click_edit');
        $htmlOff = Livewire::test(ListPurchaseOrders::class)->assertSuccessful()->html();
        $countOff = substr_count($htmlOff, $editUrl);

        session(['row_click_edit' => true]);
        $htmlOn = Livewire::test(ListPurchaseOrders::class)->assertSuccessful()->html();
        $countOn = substr_count($htmlOn, $editUrl);

        session()->forget('row_click_edit');

        $this->assertGreaterThan($countOff, $countOn);
    }

    public function test_row_click_respects_edit_authorization(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create();
        session(['row_click_edit' => true]);

        $html = Livewire::test(ListPurchaseOrders::class)->assertSuccessful()->html();

        $this->assertStringNotContainsString(
            route('filament.dashboard.resources.purchase-orders.edit', ['record' => $record]),
            $html
        );

        session()->forget('row_click_edit');
    }

    public function test_row_click_is_scoped_to_operational_resources_only(): void
    {
        // Master Data has no dedicated edit route at all (Edit is a modal) — this feature's
        // override lives on App\Filament\Pages\ListRecords, which ManageDepartments never
        // extends (it extends the separate App\Filament\Pages\ManageRecords), so toggling
        // row_click_edit on must have zero effect: the page still renders successfully,
        // vendor's own unrelated default recordAction (view-first-else-edit) still resolves.
        $this->actingAsUserWithPermissions(['department.view', 'department.edit']);
        $record = Department::factory()->create();
        session(['row_click_edit' => true]);

        $component = Livewire::test(ManageDepartments::class)->assertSuccessful()->instance();

        $this->assertSame('view', $component->getTable()->getRecordAction($record));

        session()->forget('row_click_edit');
    }

    public function test_row_click_lang_keys_exist_in_all_three_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach (['view', 'edit'] as $key) {
                $this->assertTrue(
                    trans()->has("resources/general/strings.row_click.{$key}", $locale),
                    "Missing row_click.{$key} in {$locale}"
                );
            }
        }
    }

    public function test_tables_render_stacked_on_mobile_by_default(): void
    {
        $this->actingAsUserWithPermissions(['correspondence.view']);

        $rendered = Livewire::test(ListCorrespondences::class)->assertSuccessful();

        $this->assertStringContainsString('fi-ta-table-stacked-on-mobile', $rendered->html());
    }

    public function test_stacked_table_lang_keys_exist_in_all_three_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach (['enable', 'disable'] as $key) {
                $this->assertTrue(
                    trans()->has("resources/general/strings.stacked_table.{$key}", $locale),
                    "Missing stacked_table.{$key} in {$locale}"
                );
            }
        }
    }

    public function test_language_switch_sits_between_calendar_and_work_actions(): void
    {
        $html = view('language-switch::language-switch')->render();

        $this->assertStringContainsString('style="order: 9"', $html);
    }

    public function test_nav_dock_peek_state_is_wired_across_js_css_and_lang(): void
    {
        $this->assertStringContainsString("'nav-dock-peek'", File::get(resource_path('js/filament/nav-dock.js')));
        $this->assertStringContainsString('nav-dock-peek .fi-sidebar-nav', File::get(resource_path('css/fi-custom.css')));
        $this->assertStringContainsString('position: sticky', File::get(resource_path('css/fi-custom.css')));
        $this->assertStringContainsString('.dock-min', File::get(resource_path('js/filament/nav-dock.js')));
        $this->assertStringContainsString('chevron-double-down', File::get(resource_path('views/filament/partials/dock-min.blade.php')));

        $rendered = view('filament.partials.dock-min')->render();
        $this->assertStringContainsString('class="dock-min', $rendered);

        foreach (['en', 'fa', 'fr'] as $locale) {
            $this->assertTrue(
                trans()->has('resources/general/strings.nav_dock.switch_to_peek', $locale),
                "Missing nav_dock.switch_to_peek in {$locale}"
            );
        }
    }
}
