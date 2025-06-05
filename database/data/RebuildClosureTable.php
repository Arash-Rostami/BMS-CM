<?php


use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildClosureTable extends Command
{
    protected $signature = 'closure:rebuild';
    protected $description = 'Rebuild the category_closure table';

    public function handle()
    {

        $categories = Category::all()->keyBy('id');

        foreach ($categories as $category) {
            // Insert self-relation
            DB::table('category_closure')->insert([
                'ancestor_id' => $category->id,
                'descendant_id' => $category->id,
                'depth' => 0,
                'deleted_at' => null,
            ]);

            // Insert ancestor relations
            $visited = [$category->id];
            $current = $category;
            $depth = 1;

            while ($current->parent_id && isset($categories[$current->parent_id])) {
                if (in_array($current->parent_id, $visited)) {
                    throw new \Exception("Cycle detected: " . implode(' -> ', $visited) . " -> {$current->parent_id}");
                }
                $visited[] = $current->parent_id;

                DB::table('category_closure')->insert([
                    'ancestor_id' => $current->parent_id,
                    'descendant_id' => $category->id,
                    'depth' => $depth,
                    'deleted_at' => null,
                ]);

                $current = $categories[$current->parent_id];
                $depth++;
            }
        }

        $this->info('Category closure table rebuilt successfully.');
    }
}
