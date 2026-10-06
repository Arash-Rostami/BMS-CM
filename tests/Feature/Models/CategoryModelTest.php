<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\Specification;
use App\Models\Target;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        Cache::forget('categories.hierarchy.en');
        Cache::forget('categories.hierarchy.fa');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Cache::forget('categories.hierarchy.en');
        Cache::forget('categories.hierarchy.fa');
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

    // Localization

    public function test_localized_name_resolves_by_locale(): void
    {
        $category = Category::factory()->create([
            'name' => 'دسته مالی',
            'english_name' => 'Finance Category',
        ]);

        app()->setLocale('fa');
        $this->assertSame('دسته مالی', $category->getLocalizedNameAttribute());

        app()->setLocale('en');
        $this->assertSame('Finance Category', $category->getLocalizedNameAttribute());
    }

    // HasSlug — de-duplication

    public function test_has_slug_deduplicates_matching_english_names(): void
    {
        $first = Category::factory()->create(['english_name' => 'Duplicate Slug Source']);
        $second = Category::factory()->create(['english_name' => 'Duplicate Slug Source']);

        $this->assertSame('duplicate-slug-source', $first->slug);
        $this->assertSame('duplicate-slug-source-1', $second->slug);
    }

    public function test_has_slug_regenerates_only_when_english_name_changes(): void
    {
        $category = Category::factory()->create(['english_name' => 'Stable Slug Name']);
        $originalSlug = $category->slug;

        $category->update(['description' => 'unrelated change']);

        $this->assertSame($originalSlug, $category->fresh()->slug);
    }

    // BreadCrumbs

    public function test_sort_ancestors_builds_the_full_breadcrumb_path(): void
    {
        app()->setLocale('en');

        $grandparent = Category::factory()->create(['english_name' => 'Grandparent']);
        $parent = Category::factory()->forParent($grandparent)->create(['english_name' => 'Parent']);
        $child = Category::factory()->forParent($parent)->create(['english_name' => 'Child']);

        $this->assertSame('Grandparent » Parent » Child', $child->sortAncestors());
    }

    // NestedOptions

    public function test_get_cached_hierarchy_groups_categories_by_parent_id(): void
    {
        app()->setLocale('en');

        $root = Category::factory()->create(['english_name' => 'HierarchyRoot', 'active' => true]);
        $child = Category::factory()->forParent($root)->create(['english_name' => 'HierarchyChild', 'active' => true]);

        $hierarchy = $root->getCachedHierarchy();

        $this->assertArrayHasKey(0, $hierarchy);
        $this->assertArrayHasKey($root->id, $hierarchy);
        $this->assertSame('HierarchyRoot', $hierarchy[0][$root->id]);
        $this->assertSame('HierarchyChild', $hierarchy[$root->id][$child->id]);
    }

    // Scopables

    public function test_scope_active_returns_only_active_categories(): void
    {
        $active = Category::factory()->create(['active' => true]);
        $inactive = Category::factory()->create(['active' => false]);

        $results = Category::active()->pluck('id');

        $this->assertContains($active->id, $results);
        $this->assertNotContains($inactive->id, $results);
    }

    public function test_scope_top_level_returns_only_root_categories(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->forParent($root)->create();

        $results = Category::topLevel()->pluck('id');

        $this->assertContains($root->id, $results);
        $this->assertNotContains($child->id, $results);
    }

    public function test_scope_level_filters_by_the_given_level(): void
    {
        $base = Category::factory()->create(['level' => 0]);
        $sub = Category::factory()->create(['level' => 1]);

        $results = Category::level(0)->pluck('id');

        $this->assertContains($base->id, $results);
        $this->assertNotContains($sub->id, $results);
    }

    // Relationships

    public function test_parent_and_children_relations_resolve_correctly(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains('id', $child->id));
    }

    public function test_products_relation_resolves_assigned_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->products->contains('id', $product->id));
    }

    public function test_specifications_and_targets_relations_resolve_via_morph(): void
    {
        $category = Category::factory()->create();

        $specification = Specification::factory()->forSpecifiable($category)->create();
        $target = Target::factory()->forTargetable($category)->create();

        $this->assertTrue($category->specifications->contains('id', $specification->id));
        $this->assertTrue($category->targets->contains('id', $target->id));
    }

    // Cycle guard helpers

    public function test_descendant_ids_of_returns_every_descendant_regardless_of_depth(): void
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->forParent($grandparent)->create();
        $child = Category::factory()->forParent($parent)->create();

        $descendantIds = Category::descendantIdsOf($grandparent->id);

        $this->assertContains($grandparent->id, $descendantIds);
        $this->assertContains($parent->id, $descendantIds);
        $this->assertContains($child->id, $descendantIds);
    }

    public function test_would_create_cycle_detects_self_and_descendant_parents(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();
        $unrelated = Category::factory()->create();

        $this->assertTrue(Category::wouldCreateCycle($parent->id, $parent->id));
        $this->assertTrue(Category::wouldCreateCycle($parent->id, $child->id));
        $this->assertFalse(Category::wouldCreateCycle($parent->id, $unrelated->id));
        $this->assertFalse(Category::wouldCreateCycle(null, $unrelated->id));
        $this->assertFalse(Category::wouldCreateCycle($parent->id, null));
    }

    // CategoryObserver — category_closure lifecycle

    public function test_created_inserts_a_self_link_and_ancestor_links(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $this->assertTrue(
            DB::table('category_closure')
                ->where('ancestor_id', $child->id)
                ->where('descendant_id', $child->id)
                ->where('depth', 0)
                ->exists()
        );

        $this->assertTrue(
            DB::table('category_closure')
                ->where('ancestor_id', $parent->id)
                ->where('descendant_id', $child->id)
                ->where('depth', 1)
                ->exists()
        );
    }

    public function test_updated_reparenting_resyncs_the_closure_table(): void
    {
        $oldParent = Category::factory()->create();
        $newParent = Category::factory()->create();
        $child = Category::factory()->forParent($oldParent)->create();

        $child->update(['parent_id' => $newParent->id]);

        $this->assertFalse(
            DB::table('category_closure')
                ->where('ancestor_id', $oldParent->id)
                ->where('descendant_id', $child->id)
                ->exists()
        );

        $this->assertTrue(
            DB::table('category_closure')
                ->where('ancestor_id', $newParent->id)
                ->where('descendant_id', $child->id)
                ->exists()
        );
    }

    public function test_updated_without_parent_id_change_does_not_touch_the_closure_table(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $before = DB::table('category_closure')->where('descendant_id', $child->id)->count();

        $child->update(['description' => 'a harmless edit']);

        $after = DB::table('category_closure')->where('descendant_id', $child->id)->count();

        $this->assertSame($before, $after);
    }

    public function test_restored_resyncs_the_closure_table(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $child->delete();
        DB::table('category_closure')->where('descendant_id', $child->id)->delete();

        $child->restore();

        $this->assertTrue(
            DB::table('category_closure')
                ->where('ancestor_id', $parent->id)
                ->where('descendant_id', $child->id)
                ->exists()
        );
    }

    public function test_force_deleted_removes_every_closure_row_referencing_the_category(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->forParent($parent)->create();

        $childId = $child->id;
        $child->forceDelete();

        $this->assertSame(
            0,
            DB::table('category_closure')
                ->where('ancestor_id', $childId)
                ->orWhere('descendant_id', $childId)
                ->count()
        );
    }

    public function test_creating_a_multi_level_chain_links_every_transitive_ancestor_not_just_the_direct_parent(): void
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->forParent($grandparent)->create();
        $child = Category::factory()->forParent($parent)->create();

        $this->assertTrue(
            DB::table('category_closure')->where(['ancestor_id' => $grandparent->id, 'descendant_id' => $child->id])->exists(),
            'The grandparent must be a recognized ancestor of the child via the closure table, not just the direct parent.'
        );
        $this->assertSame(0, DB::table('category_closure')->where('ancestor_id', $grandparent->id)->where('descendant_id', $grandparent->id)->value('depth'));
        $this->assertSame(1, DB::table('category_closure')->where('ancestor_id', $parent->id)->where('descendant_id', $child->id)->value('depth'));
        $this->assertSame(2, DB::table('category_closure')->where('ancestor_id', $grandparent->id)->where('descendant_id', $child->id)->value('depth'));
    }

    public function test_reparenting_a_category_with_its_own_children_cascades_the_closure_update_to_them_too(): void
    {
        $root = Category::factory()->create();
        $middle = Category::factory()->forParent($root)->create();
        $leaf = Category::factory()->forParent($middle)->create();
        $newRoot = Category::factory()->create();

        $middle->update(['parent_id' => $newRoot->id]);

        $this->assertTrue(
            DB::table('category_closure')->where(['ancestor_id' => $newRoot->id, 'descendant_id' => $leaf->id])->exists(),
            'The leaf (a child of the reparented category) must now be a recognized descendant of the new root too, not just the reparented category itself.'
        );
        $this->assertFalse(
            DB::table('category_closure')->where(['ancestor_id' => $root->id, 'descendant_id' => $leaf->id])->exists(),
            'The old root must no longer be a recognized ancestor of the leaf.'
        );
        $this->assertFalse(
            DB::table('category_closure')->where(['ancestor_id' => $root->id, 'descendant_id' => $middle->id])->exists(),
            'The old root must no longer be a recognized ancestor of the reparented category itself.'
        );
        $this->assertTrue(
            DB::table('category_closure')->where(['ancestor_id' => $middle->id, 'descendant_id' => $leaf->id])->exists(),
            'The relationship within the moved subtree (middle -> leaf) must survive the reparent untouched.'
        );
    }

    // UserStamps + soft deletes

    public function test_creation_stamps_the_authenticated_user_and_soft_delete_keeps_the_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = Category::factory()->create();
        $this->assertSame($user->id, $category->creator->id);

        $category->delete();

        $this->assertNull(Category::find($category->id));
        $this->assertNotNull(Category::withTrashed()->find($category->id));
        $this->assertTrue($category->fresh()->trashed());
    }
}
