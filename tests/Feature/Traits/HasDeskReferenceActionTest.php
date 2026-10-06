<?php

namespace Tests\Feature\Traits;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Traits\HasDeskReferenceAction;
use App\Models\Bank;
use App\Models\DeskReference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Filament\Traits\HasDeskReferenceAction::getDeskReferenceHeaderAction() resolves
 * a config+lang-driven header Action, or null when the resource isn't registered in
 * config/desk-reference.php, and tracks read/unread per (group_key, version) — NOT
 * per resource, so acknowledging one sibling module clears the reminder on every
 * other resource sharing that group (filamentPattern.md §1.27). Composed on 7
 * unrelated resources (BankProfile, Custom, Payment, RegisteredOrder, PurchaseRequest,
 * PurchaseOrder, Shipment) — cross-cutting, no existing coverage.
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
}

class HasDeskReferenceActionBankProbe
{
    use HasDeskReferenceAction;

    public static function getModel(): string
    {
        return Bank::class;
    }
}
