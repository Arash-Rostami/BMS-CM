<?php

namespace Tests\Feature\Services;

use App\Services\CodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CodeGeneratorTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = 'PR-'.now()->format('ymd');

        Schema::dropIfExists('purchase_requests');
        Schema::create('purchase_requests', function ($table) {
            $table->id();
            $table->string('pr_number')->unique();
        });
    }

    private function seedRow(string $code): void
    {
        DB::table('purchase_requests')->insert(['pr_number' => $code]);
    }

    public function test_empty_table_returns_bare_base(): void
    {
        $this->assertSame($this->base, CodeGenerator::generate('pr_number'));
    }

    public function test_second_after_bare_base_returns_base_1(): void
    {
        $this->seedRow($this->base);

        $this->assertSame("{$this->base}-1", CodeGenerator::generate('pr_number'));
    }

    public function test_third_returns_base_2(): void
    {
        $this->seedRow($this->base);
        $this->seedRow("{$this->base}-1");

        $this->assertSame("{$this->base}-2", CodeGenerator::generate('pr_number'));
    }

    public function test_lex_sort_fix_at_ten(): void
    {
        $this->seedRow($this->base);
        for ($i = 1; $i <= 9; $i++) {
            $this->seedRow("{$this->base}-{$i}");
        }

        $this->assertSame("{$this->base}-10", CodeGenerator::generate('pr_number'));
    }

    public function test_numeric_max_after_ten(): void
    {
        $this->seedRow($this->base);
        for ($i = 1; $i <= 10; $i++) {
            $this->seedRow("{$this->base}-{$i}");
        }

        $this->assertSame("{$this->base}-11", CodeGenerator::generate('pr_number'));
    }
}
