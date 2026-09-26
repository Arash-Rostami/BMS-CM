<?php

namespace Tests\Feature\Filament;

use App\Livewire\CalendarToggle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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
        $this->assertStringContainsString('style="order: 6"', $panel);
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
}
