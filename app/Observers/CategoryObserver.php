<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryObserver
{
    /**
     * Handle the Category "created" event.
     */
    public function created(Category $category): void
    {
        $this->syncClosure($category);
    }

    /**
     * Handle the Category "updated" event.
     */
    public function updated(Category $category): void
    {
        if ($category->isDirty('parent_id')) {
            $this->syncClosure($category);
        }
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        //
    }

    /**
     * Handle the Category "restored" event.
     */
    public function restored(Category $category): void
    {
        $this->syncClosure($category);
    }

    /**
     * Handle the Category "force deleted" event.
     */
    public function forceDeleted(Category $category): void
    {
        DB::table('category_closure')
            ->where('ancestor_id', $category->id)
            ->orWhere('descendant_id', $category->id)
            ->delete();
    }

    protected function syncClosure(Category $category): void
    {
        DB::table('category_closure')->updateOrInsert(
            ['ancestor_id' => $category->id, 'descendant_id' => $category->id],
            ['depth' => 0]
        );

        $subtree = DB::table('category_closure')
            ->where('ancestor_id', $category->id)
            ->pluck('depth', 'descendant_id');

        $subtreeIds = $subtree->keys()->all();

        DB::table('category_closure')
            ->whereIn('descendant_id', $subtreeIds)
            ->whereNotIn('ancestor_id', $subtreeIds)
            ->delete();

        if ($parentId = $category->parent_id) {
            $this->createAncestorLinks($parentId, $subtree);
        }
    }

    /**
     * Relinks an entire subtree (the reparented category plus every one of its
     * own descendants) onto its new parent's ancestor chain. A reparent only
     * ever changes how the subtree attaches to the rest of the tree — the
     * relationships WITHIN the subtree are untouched, so this only needs to
     * replace each node's links to ancestors OUTSIDE the subtree.
     *
     * @param  Collection<int, int>  $subtreeDepths  descendant_id => depth from the reparented category (0 for itself)
     */
    private function createAncestorLinks(mixed $parentId, Collection $subtreeDepths): void
    {
        $newAncestors = DB::table('category_closure')
            ->where('descendant_id', $parentId)
            ->get(['ancestor_id', 'depth']);

        $batch = [];

        foreach ($newAncestors as $ancestor) {
            foreach ($subtreeDepths as $descendantId => $depthFromCategory) {
                $batch[] = [
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $descendantId,
                    'depth' => $ancestor->depth + 1 + $depthFromCategory,
                ];
            }
        }

        if ($batch !== []) {
            DB::table('category_closure')->insert($batch);
        }
    }
}
