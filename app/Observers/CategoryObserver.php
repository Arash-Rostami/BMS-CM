<?php

namespace App\Observers;

use App\Models\Category;
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
        $this->removeOldLinks($category);

        $this->createSelfLink($category);

        if ($parentId = $category->parent_id) {
            $this->createAncestorLinks($parentId, $category);
        }
    }

    /**
     * @param Category $category
     * @return void
     */
    private function removeOldLinks(Category $category): void
    {
        DB::table('category_closure')
            ->where('descendant_id', $category->id)
            ->delete();
    }

    /**
     * @param Category $category
     * @return void
     */
    private function createSelfLink(Category $category): void
    {
        DB::table('category_closure')->insert([
            'ancestor_id' => $category->id,
            'descendant_id' => $category->id,
            'depth' => 0,
        ]);
    }

    /**
     * @param mixed $parentId
     * @param Category $category
     * @return void
     */
    private function createAncestorLinks(mixed $parentId, Category $category): void
    {
        $ancestors = DB::table('category_closure')
            ->where('descendant_id', $parentId)
            ->get(['ancestor_id', 'depth']);

        $batch = $ancestors->map(fn($anc) => [
            'ancestor_id' => $anc->ancestor_id,
            'descendant_id' => $category->id,
            'depth' => $anc->depth + 1,
        ])->all();

        DB::table('category_closure')->insert($batch);
    }
}
