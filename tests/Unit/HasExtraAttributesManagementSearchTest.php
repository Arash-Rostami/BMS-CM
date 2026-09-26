<?php

namespace Tests\Unit;

use App\Filament\Traits\HasExtraAttributesManagement;
use App\Models\PurchaseRequest;
use Tests\TestCase;

class HasExtraAttributesManagementSearchTestDouble
{
    use HasExtraAttributesManagement;
}

class HasExtraAttributesManagementSearchTest extends TestCase
{
    public function test_with_extra_attributes_search_appends_key_and_value_dot_paths(): void
    {
        $result = HasExtraAttributesManagementSearchTestDouble::withExtraAttributesSearch(['pr_number', 'rejection_reason']);

        $this->assertSame(
            ['pr_number', 'rejection_reason', 'extraAttributes.key', 'extraAttributes.value'],
            $result
        );
    }

    public function test_with_extra_attributes_search_does_not_mutate_an_empty_list(): void
    {
        $this->assertSame(
            ['extraAttributes.key', 'extraAttributes.value'],
            HasExtraAttributesManagementSearchTestDouble::withExtraAttributesSearch([])
        );
    }

    public function test_or_where_extra_attributes_match_builds_a_where_has_on_the_extra_attributes_relation(): void
    {
        $query = PurchaseRequest::query()->where('pr_number', 'like', '%PR-1%');

        $result = HasExtraAttributesManagementSearchTestDouble::orWhereExtraAttributesMatch($query, 'needle');

        $sql = strtolower($result->toSql());
        $bindings = $result->getBindings();

        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('entity_attributes', $sql);
        $this->assertContains('%needle%', $bindings);
    }
}
