<?php

namespace Tests\Feature\Rules;

use App\Rules\ValidAttachment;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Storage;
use Tests\TestCase;

class ValidAttachmentTest extends TestCase
{
    private const DISK = 'tmp-for-tests';

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(self::DISK);
    }

    private function failures(mixed $value): array
    {
        $messages = [];

        (new ValidAttachment())->validate('attachments', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    private function makeFile(string $originalName, string $contents): TemporaryUploadedFile
    {
        $filename = str()->random(30).'-meta'.str(base64_encode($originalName))->replace('/', '_').'-.pdf';

        Storage::disk(self::DISK)->put('livewire-tmp/'.$filename, $contents);

        return new TemporaryUploadedFile($filename, self::DISK);
    }

    public function test_a_value_that_is_not_a_temporary_uploaded_file_is_ignored(): void
    {
        $this->assertSame([], $this->failures('not-an-uploaded-file'));
    }

    public function test_a_filename_outside_the_allowed_character_set_fails(): void
    {
        $cyrillic = $this->makeFile('Отчёт.pdf', self::PDF);
        $emoji = $this->makeFile('invoice 😀.pdf', self::PDF);

        $this->assertNotEmpty($this->failures($cyrillic), 'A filename with characters outside Latin/Arabic/digits/common punctuation must be rejected.');
        $this->assertNotEmpty($this->failures($emoji), 'An emoji in the filename must be rejected.');
    }

    public function test_a_filename_using_the_full_allowed_character_set_passes(): void
    {
        $name = 'گزارش sales 2024 (final) [v2] & notes, extra-v1.2_final.pdf';
        $file = $this->makeFile($name, self::PDF);

        $this->assertSame([], $this->failures($file), 'A valid PDF named with only allowed characters must pass every check.');
    }

    public function test_a_file_above_the_size_limit_fails(): void
    {
        $file = $this->makeFile('large.pdf', str_repeat('a', ValidAttachment::MAX_SIZE_KB * 1024 + 1));

        $this->assertNotEmpty($this->failures($file), 'A file larger than MAX_SIZE_KB must be rejected.');
    }

    public function test_a_file_with_a_disallowed_mime_type_fails(): void
    {
        $file = $this->makeFile('notes.txt', 'plain text content');

        $this->assertNotEmpty($this->failures($file), 'A file whose mime type is not in ALLOWED_TYPES must be rejected.');
    }

    public function test_a_small_pdf_with_a_valid_name_passes(): void
    {
        $file = $this->makeFile('invoice.pdf', self::PDF);

        $this->assertSame([], $this->failures($file), 'A PDF inside the size limit with an allowed filename must pass.');
    }
}