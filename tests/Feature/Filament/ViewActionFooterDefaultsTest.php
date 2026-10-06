<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\ListPurchaseRequests;
use App\Filament\Resources\Operational\RegisteredOrderResource\Pages\EditRegisteredOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\PurchaseOrdersRelationManager;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\View\ActionsRenderHook;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Project-wide integrity check for App\Configurators\FilamentViewActionDefaults, registered
 * globally in AppServiceProvider::boot() via ViewAction::configureUsing(...) + a
 * FilamentView::registerRenderHook(...) call. Not folded into any single resource's test file
 * since this affects EVERY ViewAction app-wide, not one resource's own logic — same category
 * as TopbarTest.php's other cross-cutting checks.
 *
 * Two mechanisms, same configurator:
 * - Footer buttons (extraModalFooterActions()): verified manually beforehand (not reproducible
 *   as a single assertion) that a nested EditAction auto-resolves its URL through the hosting
 *   Resource's own getDefaultActionUrl() hook (vendor RelationManager.php/Page.php), and both
 *   nested actions correctly inherit this project's HasResourcePermissions authorization —
 *   confirmed via direct inspection of the resolved action objects' isVisible()/isAuthorized()/
 *   getUrl(), not HTML string-matching (which gave false positives/negatives against the 25+
 *   pre-existing row-level Edit/Delete buttons already on the same page).
 * - Header icon shortcuts (ActionsRenderHook::MODAL_CUSTOM_CONTENT_BEFORE): clones the same
 *   footer action instances into icon-only buttons, rendered via render hook + a fi-custom.css
 *   absolute-position rule (`.fi-modal-header-quick-actions`), not a vendor view override. A
 *   first attempt at this used modalHeading()+HtmlString and genuinely failed; the root cause
 *   was the test probe, not the mechanism — callTableAction() calls then unmounts a no-op
 *   ViewAction before ->html() is captured, so the modal partial was never in the response at
 *   all. mountTableAction() (mount without unmounting) is the correct probe — see
 *   filamentPattern.md §1.26a.
 */
class ViewActionFooterDefaultsTest extends TestCase
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

    public function test_purchase_order_view_modal_footer_shows_edit_and_delete_with_the_real_edit_url(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.edit', 'purchase_order.delete']);
        $record = PurchaseOrder::factory()->create();

        $component = Livewire::test(ListPurchaseOrders::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);
        $footerActions = collect($action->getExtraModalFooterActions())->keyBy(fn ($a) => $a->getName());

        $this->assertTrue($footerActions['edit']->isVisible());
        $this->assertTrue($footerActions['delete']->isVisible());
        $this->assertStringContainsString(
            route('filament.dashboard.resources.purchase-orders.edit', $record),
            $footerActions['edit']->getUrl()
        );
    }

    public function test_purchase_order_view_modal_footer_hides_edit_and_delete_without_permission(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create();

        $component = Livewire::test(ListPurchaseOrders::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);
        $footerActions = collect($action->getExtraModalFooterActions())->keyBy(fn ($a) => $a->getName());

        $this->assertFalse($footerActions['edit']->isVisible());
        $this->assertFalse($footerActions['delete']->isVisible());
    }

    public function test_the_default_is_genuinely_global_not_purchase_order_specific(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view', 'purchase_request.edit', 'purchase_request.delete']);
        $record = PurchaseRequest::factory()->create();

        $component = Livewire::test(ListPurchaseRequests::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);
        $footerActions = collect($action->getExtraModalFooterActions())->keyBy(fn ($a) => $a->getName());

        $this->assertTrue($footerActions['edit']->isVisible());
        $this->assertTrue($footerActions['delete']->isVisible());
    }

    public function test_footer_actions_resolve_correctly_inside_a_relation_manager_too(): void
    {
        $this->actingAsUserWithPermissions(['registered_order.view', 'purchase_order.view', 'purchase_order.edit']);
        $owner = RegisteredOrder::factory()->create();
        $record = PurchaseOrder::factory()->create();
        $owner->purchaseOrders()->attach($record->id);

        $component = Livewire::test(PurchaseOrdersRelationManager::class, [
            'ownerRecord' => $owner,
            'pageClass' => EditRegisteredOrder::class,
        ])->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);
        $footerActions = collect($action->getExtraModalFooterActions())->keyBy(fn ($a) => $a->getName());

        $this->assertTrue($footerActions['edit']->isVisible());
        $this->assertStringContainsString(
            route('filament.dashboard.resources.purchase-orders.edit', $record),
            $footerActions['edit']->getUrl()
        );
    }

    public function test_view_modal_header_render_hook_shows_icon_only_edit_and_delete_with_permission(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view', 'purchase_order.edit', 'purchase_order.delete']);
        $record = PurchaseOrder::factory()->create();

        $component = Livewire::test(ListPurchaseOrders::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);

        $html = FilamentView::renderHook(ActionsRenderHook::MODAL_CUSTOM_CONTENT_BEFORE, data: ['action' => $action])->toHtml();

        $this->assertStringContainsString('fi-modal-header-quick-actions', $html);
        $this->assertSame(2, substr_count($html, 'fi-icon-btn'));
    }

    public function test_view_modal_header_render_hook_hides_icon_shortcuts_without_permission(): void
    {
        $this->actingAsUserWithPermissions(['purchase_order.view']);
        $record = PurchaseOrder::factory()->create();

        $component = Livewire::test(ListPurchaseOrders::class)->callTableAction('view', $record)->instance();
        $action = $component->getTable()->getAction('view')->record($record);

        $html = FilamentView::renderHook(ActionsRenderHook::MODAL_CUSTOM_CONTENT_BEFORE, data: ['action' => $action])->toHtml();

        $this->assertStringNotContainsString('fi-modal-header-quick-actions', $html);
    }

    public function test_view_modal_header_render_hook_ignores_non_view_actions(): void
    {
        $html = FilamentView::renderHook(
            ActionsRenderHook::MODAL_CUSTOM_CONTENT_BEFORE,
            data: ['action' => \Filament\Actions\EditAction::make('edit')]
        )->toHtml();

        $this->assertSame('', $html);
    }

}
