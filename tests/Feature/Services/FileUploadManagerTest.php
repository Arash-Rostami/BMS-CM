<?php

namespace Tests\Feature\Services;

use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\User;
use App\Services\FileUploadManager;
use App\Services\SmartCacheManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadManagerTest extends TestCase
{
    private User $user;

    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        SmartCacheManager::invalidate('Status');
        DB::beginTransaction();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Storage::disk('public')->delete($this->paths);
        SmartCacheManager::invalidate('Status');
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

    private function uploadedStatus(): Status
    {
        return Status::findBy('Attachment Status', 'Uploaded')
            ?? Status::factory()->create([
                'type' => 'Attachment Status',
                'english_type' => 'Attachment Status',
                'name' => 'Uploaded',
                'english_name' => 'Uploaded',
            ]);
    }

    private function storeTemporary(string $originalName): string
    {
        $path = (new FileUploadManager)->storeTemporary(UploadedFile::fake()->create($originalName, 20));

        $this->paths[] = $path;

        return $path;
    }

    public function test_store_temporary_keeps_the_original_name_and_a_unique_suffix(): void
    {
        $path = $this->storeTemporary('my report.pdf');

        $this->assertStringStartsWith('temp/', $path);
        $this->assertStringContainsString('my%20report__', $path);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_process_temporary_moves_the_file_and_creates_the_attachment_row(): void
    {
        $status = $this->uploadedStatus();
        $record = PurchaseRequest::factory()->create();
        $tempPath = $this->storeTemporary('my report.pdf');

        $manager = new FileUploadManager;
        $this->assertSame($manager, $manager->processTemporaryFiles($record, [$tempPath]));

        $attachment = $record->attachments()->first();
        $this->paths[] = $attachment->path;
        $this->assertNotNull($attachment);
        $this->assertSame('my report.pdf', $attachment->name);
        $this->assertStringStartsWith('attachments/purchaseRequest/', $attachment->path);
        $this->assertSame($this->user->id, $attachment->user_id);
        $this->assertSame($status->id, $attachment->status_id);
        $this->assertSame('Uploaded', $attachment->status->english_name);
        $this->assertTrue(Storage::disk('public')->exists($attachment->path));
        $this->assertFalse(Storage::disk('public')->exists($tempPath));
    }

    public function test_process_temporary_passes_non_temp_paths_through_without_creating_rows(): void
    {
        $this->uploadedStatus();
        $record = PurchaseRequest::factory()->create();

        (new FileUploadManager)->processTemporaryFiles($record, ['attachments/existing.pdf']);

        $this->assertSame(0, $record->attachments()->count());
    }

    public function test_process_temporary_deletes_stale_attachments_missing_from_the_submitted_paths(): void
    {
        $this->uploadedStatus();
        $record = PurchaseRequest::factory()->create();

        $stalePath = 'attachments/purchaseRequests/stale-'.uniqid().'.pdf';
        Storage::disk('public')->put($stalePath, 'stale');
        $this->paths[] = $stalePath;
        $record->attachments()->create([
            'name' => 'stale.pdf',
            'path' => $stalePath,
            'type' => 'application/pdf',
            'user_id' => $this->user->id,
        ]);

        $tempPath = $this->storeTemporary('fresh.pdf');
        (new FileUploadManager)->processTemporaryFiles($record, [$tempPath]);

        $this->paths = array_merge($this->paths, $record->attachments()->pluck('path')->all());

        $this->assertNull($record->attachments()->where('path', $stalePath)->first());
        $this->assertFalse(Storage::disk('public')->exists($stalePath));
        $this->assertSame(1, $record->attachments()->count());
    }
}