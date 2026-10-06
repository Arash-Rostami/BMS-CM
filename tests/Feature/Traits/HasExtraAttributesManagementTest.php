<?php

namespace Tests\Feature\Traits;

use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Traits\HasExtraAttributesManagement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Tests\TestCase;

/**
 * App\Filament\Traits\HasExtraAttributesManagement's two static search helpers
 * (withExtraAttributesSearch/orWhereExtraAttributesMatch) already have dedicated
 * coverage in tests/Unit/HasExtraAttributesManagementSearchTest.php. This file adds
 * the piece that isn't covered anywhere: the Tab-building contract and, specifically,
 * the infolist `value` entry's formatStateUsing() null/string/array branches —
 * filamentPattern.md §1.6 documents this exact null-handling as "a real bug,
 * reproduced live 2026-09-22" (json_encode(null) === "null", a 4-character string,
 * silently defeating the infolist's placeholder). Composed on every one of this
 * project's EAV-backed resources and RelationManagers — genuinely cross-cutting.
 * Reflection on `childComponents` (same technique as testPattern.md §3f) walks the
 * static schema tree without needing a live Livewire container.
 */
class HasExtraAttributesManagementTest extends TestCase
{
    private function rawChildren(object $component): array
    {
        if ($component instanceof Schema) {
            $property = new \ReflectionProperty($component, 'components');
            $property->setAccessible(true);
            $children = $property->getValue($component);
        } else {
            $property = new \ReflectionProperty($component, 'childComponents');
            $property->setAccessible(true);
            $children = $property->getValue($component)['default'] ?? [];
        }

        if ($children instanceof Schema) {
            return $this->rawChildren($children);
        }

        return is_array($children) ? $children : [];
    }

    private function valueEntry(): TextEntry
    {
        $tab = PurchaseRequestResource::getExtraAttributesInfolistTab();
        $section = $this->rawChildren($tab)[0];
        $repeatable = $this->rawChildren($section)[0];
        $entries = $this->rawChildren($repeatable);

        return collect($entries)->first(fn ($entry) => $entry->getName() === 'value');
    }

    public function test_purchase_request_resource_composes_the_trait(): void
    {
        $this->assertContains(HasExtraAttributesManagement::class, class_uses_recursive(PurchaseRequestResource::class));
    }

    public function test_form_tab_key_and_icon_are_stable_across_every_composing_resource(): void
    {
        $tab = PurchaseRequestResource::getExtraAttributesFormTab();

        $this->assertSame('heroicon-o-puzzle-piece', $tab->getIcon());
    }

    public function test_infolist_tab_badge_counts_extra_attributes_and_is_null_when_empty(): void
    {
        $tab = PurchaseRequestResource::getExtraAttributesInfolistTab();

        $property = new \ReflectionProperty($tab, 'badge');
        $property->setAccessible(true);
        $badge = $property->getValue($tab);

        $withNone = new class
        {
            public $extraAttributes;

            public function __construct()
            {
                $this->extraAttributes = collect();
            }
        };
        $withTwo = new class
        {
            public $extraAttributes;

            public function __construct()
            {
                $this->extraAttributes = collect([1, 2]);
            }
        };

        $this->assertNull($badge($withNone));
        $this->assertSame(2, $badge($withTwo));
    }

    public function test_value_entry_format_state_using_routes_a_plain_string_through_unchanged(): void
    {
        $closure = $this->rawFormatStateUsing($this->valueEntry());

        $this->assertSame('hello world', $closure('hello world'));
    }

    public function test_value_entry_format_state_using_routes_null_to_an_empty_string_not_the_literal_null(): void
    {
        $closure = $this->rawFormatStateUsing($this->valueEntry());

        $this->assertSame('', $closure(null));
        $this->assertNotSame('null', $closure(null));
    }

    public function test_value_entry_format_state_using_json_encodes_any_other_type(): void
    {
        $closure = $this->rawFormatStateUsing($this->valueEntry());

        $this->assertSame(json_encode(['a' => 1], JSON_UNESCAPED_UNICODE), $closure(['a' => 1]));
        $this->assertSame('42', $closure(42));
    }

    private function rawFormatStateUsing(TextEntry $entry): \Closure
    {
        $property = new \ReflectionProperty($entry, 'formatStateUsing');
        $property->setAccessible(true);

        return $property->getValue($entry);
    }
}
