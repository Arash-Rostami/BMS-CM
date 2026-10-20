<?php

namespace Tests\Feature\Traits;

use App\Models\BankProfile;
use App\Models\CalendarRule;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Traits\General\LogsAudit;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\Calendar\CalendarActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LogsAuditTest extends TestCase
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

    public static function auditedModels(): array
    {
        return [
            'PurchaseRequest' => [PurchaseRequest::class, 'total_estimated_cost', '123.45678'],
            'RegisteredOrder' => [RegisteredOrder::class, 'contract_no', 'audit-changed-value'],
            'PurchaseOrder' => [PurchaseOrder::class, 'incoterms', 'audit-changed-value'],
            'Payment' => [Payment::class, 'iban', 'audit-changed-value'],
            'Shipment' => [Shipment::class, 'bl_number', 'audit-changed-value'],
            'Custom' => [Custom::class, 'declaration_no', 'audit-changed-value'],
            'BankProfile' => [BankProfile::class, 'supply_source', 'audit-changed-value'],
            'ProformaInvoice' => [ProformaInvoice::class, 'contract_no', 'audit-changed-value'],
        ];
    }

    private function logs(Model $record): Collection
    {
        return Activity::query()
            ->inLog('audit')
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->orderBy('id')
            ->get();
    }

    public function test_the_eight_audited_models_compose_the_trait_and_correspondence_does_not(): void
    {
        foreach (AuditChanges::MODELS as $class) {
            $this->assertContains(LogsAudit::class, class_uses_recursive($class));
        }

        $this->assertCount(8, AuditChanges::MODELS);
        $this->assertContains(ProformaInvoice::class, AuditChanges::MODELS);
        $this->assertNotContains(LogsAudit::class, class_uses_recursive(\App\Models\Correspondence::class));
    }

    public function test_the_audit_log_name_is_a_constant_shared_by_every_consumer(): void
    {
        $this->assertSame('audit', PurchaseRequest::AUDIT_LOG);
        $this->assertSame('audit', AuditChanges::LOG_NAME);
        $this->assertNotSame(CalendarActivity::LOG_NAME, AuditChanges::LOG_NAME);
    }

    #[DataProvider('auditedModels')]
    public function test_creating_a_record_logs_one_audit_row_without_nulls_or_stamps(string $class): void
    {
        $record = $class::factory()->create();

        $rows = $this->logs($record);
        $created = $rows->first();

        $this->assertCount(1, $rows);
        $this->assertSame('created', $created->event);
        $this->assertSame('audit', $created->log_name);
        $attributes = $created->properties->get('attributes');
        $this->assertNotEmpty($attributes);
        $this->assertSame([], array_filter($attributes, fn ($value): bool => $value === null));
        $this->assertSame([], array_diff(array_keys($attributes), $class::auditColumns()));
    }

    #[DataProvider('auditedModels')]
    public function test_updating_a_listed_column_logs_only_that_column_with_old_and_new_values(string $class, string $column, string $value): void
    {
        $record = $class::factory()->create();
        $before = $record->{$column};

        $record->update([$column => $value]);

        $updated = $this->logs($record)->firstWhere('event', 'updated');

        $this->assertNotNull($updated);
        $this->assertSame([$column => $value], $updated->properties->get('attributes'));
        $this->assertSame([$column => $before], $updated->properties->get('old'));
    }

    #[DataProvider('auditedModels')]
    public function test_the_audited_columns_exist_and_never_include_status_stamps_or_free_text(string $class): void
    {
        $columns = $class::auditColumns();
        $unlogged = ['status_id', 'notes', 'body', 'docs', 'user_id', 'updated_by_id', 'deleted_at'];

        $this->assertSame([], array_diff($columns, (new $class)->getFillable()));
        $this->assertSame([], array_intersect($columns, $unlogged));
        $this->assertSame([], array_intersect($columns, method_exists($class, 'statusHistoryColumns') ? $class::statusHistoryColumns() : []));
    }

    public function test_a_notes_only_or_status_only_save_logs_nothing_while_status_history_still_records_it(): void
    {
        $record = PurchaseRequest::factory()->create();
        $baseline = $this->logs($record)->count();
        $status = Status::factory()->create();

        $record->update(['notes' => 'only a note']);
        $record->update(['status_id' => $status->id]);

        $this->assertSame($baseline, $this->logs($record)->count());
        $this->assertSame(1, $record->statusHistories()->where('to_status_id', $status->id)->count());
    }

    public function test_proforma_invoice_notes_and_auto_code_are_not_audited_while_listed_fields_still_log(): void
    {
        $record = ProformaInvoice::factory()->create();
        $baseline = $this->logs($record)->count();

        $record->update(['notes' => 'edited note']);
        $this->assertSame($baseline, $this->logs($record)->count());

        $record->update(['contract_no' => 'audit-contract', 'notes' => 'again']);
        $updated = $this->logs($record)->firstWhere('event', 'updated');

        $this->assertSame(['contract_no' => 'audit-contract'], $updated->properties->get('attributes'));
        $this->assertArrayNotHasKey('invoice_no', $this->logs($record)->first()->properties->get('attributes'));
    }

    public function test_a_no_op_save_and_a_stamp_only_change_log_nothing(): void
    {
        $record = PurchaseRequest::factory()->create();
        $baseline = $this->logs($record)->count();

        $record->save();
        $record->user_id = User::factory()->create()->id;
        $record->save();

        $this->assertSame($baseline, $this->logs($record)->count());
    }

    public function test_payment_bank_details_changes_are_logged(): void
    {
        $payment = Payment::factory()->create();

        $payment->update(['iban' => 'IR0000000000000000000000', 'account_no' => '1234567']);

        $updated = $this->logs($payment)->firstWhere('event', 'updated');

        $this->assertSame('IR0000000000000000000000', $updated->properties->get('attributes')['iban']);
        $this->assertSame('1234567', $updated->properties->get('attributes')['account_no']);
    }

    public function test_soft_delete_and_restore_each_log_exactly_one_small_row(): void
    {
        $record = PurchaseRequest::factory()->create();

        $record->delete();
        $record->restore();

        $events = $this->logs($record)->pluck('event')->all();
        $this->assertSame(['created', 'deleted', 'restored'], $events);

        $deleted = $this->logs($record)->firstWhere('event', 'deleted');
        $restored = $this->logs($record)->firstWhere('event', 'restored');

        $this->assertSame(['deleted_at'], array_keys($deleted->properties->get('attributes')));
        $this->assertNotNull($deleted->properties->get('attributes')['deleted_at']);
        $this->assertSame(['deleted_at' => null], $restored->properties->get('attributes'));
    }

    public function test_the_causer_is_the_authenticated_user_and_null_for_system_writes(): void
    {
        $user = User::factory()->create();
        $guest = PurchaseRequest::factory()->create();
        $this->actingAs($user);
        $acted = PurchaseRequest::factory()->create();

        $this->assertNull($this->logs($guest)->first()->causer_id);
        $this->assertSame($user->id, $this->logs($acted)->first()->causer_id);
    }

    public function test_calendar_and_audit_logs_never_mix(): void
    {
        $record = PurchaseRequest::factory()->create();
        $rule = CalendarRule::factory()->create();
        $auditBefore = $this->logs($record)->count();
        $calendar = fn (): int => Activity::query()->inLog('calendar')->where('subject_id', $record->id)->where('subject_type', $record->getMorphClass())->count();
        $calendarBefore = $calendar();

        app(CalendarActivity::class)->log('matched', $record, $rule, ['label' => 'x']);

        $this->assertSame($auditBefore, $this->logs($record)->count());
        $this->assertSame($calendarBefore + 1, $calendar());
        $this->assertCount($calendar(), app(CalendarActivity::class)->forRecord($record));
        $this->assertSame([], $this->logs($record)->where('log_name', '!=', 'audit')->all());
    }
}
