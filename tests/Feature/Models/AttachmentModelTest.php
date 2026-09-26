<?php

namespace Tests\Feature\Models;

use App\Models\Attachment;
use App\Models\PurchaseRequest;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttachmentModelTest extends TestCase
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

    private function attachmentStatus(string $englishName): Status
    {
        return Status::factory()->create([
            'type' => Attachment::TYPE_ATTACHMENT,
            'english_type' => Attachment::TYPE_ATTACHMENT,
            'name' => $englishName,
            'english_name' => $englishName,
        ]);
    }

    public function test_status_relation_is_scoped_to_attachment_status_type(): void
    {
        $wrongType = Status::factory()->create(['english_type' => 'Registered Order Status']);
        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => $wrongType->id]);

        $this->assertNull($attachment->fresh()->status);
    }

    public function test_status_relation_resolves_when_english_type_matches(): void
    {
        $status = $this->attachmentStatus(Attachment::STATUS_UPLOADED);
        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => $status->id]);

        $this->assertSame($status->id, $attachment->fresh()->status->id);
    }

    public function test_is_uploaded_is_true_only_for_the_uploaded_status(): void
    {
        $uploaded = $this->attachmentStatus(Attachment::STATUS_UPLOADED);
        $archived = $this->attachmentStatus(Attachment::STATUS_ARCHIVED);

        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => $uploaded->id]);
        $this->assertTrue($attachment->fresh()->isUploaded());

        $attachment->update(['status_id' => $archived->id]);
        $this->assertFalse($attachment->fresh()->isUploaded());
    }

    public function test_is_superseded_is_true_only_for_the_superseded_status(): void
    {
        $superseded = $this->attachmentStatus(Attachment::STATUS_SUPERSEDED);
        $uploaded = $this->attachmentStatus(Attachment::STATUS_UPLOADED);

        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => $superseded->id]);
        $this->assertTrue($attachment->fresh()->isSuperseded());

        $attachment->update(['status_id' => $uploaded->id]);
        $this->assertFalse($attachment->fresh()->isSuperseded());
    }

    public function test_is_archived_is_true_only_for_the_archived_status(): void
    {
        $archived = $this->attachmentStatus(Attachment::STATUS_ARCHIVED);
        $uploaded = $this->attachmentStatus(Attachment::STATUS_UPLOADED);

        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => $archived->id]);
        $this->assertTrue($attachment->fresh()->isArchived());

        $attachment->update(['status_id' => $uploaded->id]);
        $this->assertFalse($attachment->fresh()->isArchived());
    }

    public function test_status_helpers_are_false_without_a_status(): void
    {
        $attachment = Attachment::factory()->forAttachable(PurchaseRequest::factory()->create())->create(['status_id' => null]);

        $this->assertFalse($attachment->isUploaded());
        $this->assertFalse($attachment->isSuperseded());
        $this->assertFalse($attachment->isArchived());
    }
}
