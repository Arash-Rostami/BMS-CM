<?php


use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProductAttributes extends Command
{
    protected $signature = 'migrate:product-attributes';
    protected $description = 'Parse products.attributes and populate product_attributes table with inferred keys';

    public function handle()
    {
        $products = Product::withoutGlobalScopes()->get();

            foreach ($products as $p) {
                $attrs = $p->attributes ?? [];
                if (!is_array($attrs) || empty($attrs)) continue;

                $toKeep = $attrs;
                $pairs  = [];

                foreach ($attrs as $val) {
                    $val = trim($val);

                    // 1. Purity (e.g. "85%")
                    if (preg_match('/^\d+(\.\d+)?\s*%$/', $val)) {
                        $pairs['purity'] = $val;
                    }
                    // 2. 3D Dimensions (e.g. "2440*1220*18")
                    elseif (preg_match('/^\d{3,4}\*\d{3,4}\*\d{1,3}$/', $val)) {
                        [$l, $w, $t] = explode('*', $val);
                        $pairs['length_mm']    = $l;
                        $pairs['width_mm']     = $w;
                        $pairs['thickness_mm'] = $t;
                    }
                    // 3. Format (e.g. "70*100")
                    elseif (preg_match('/^\d{2,3}\*\d{2,3}$/', $val)) {
                        $pairs['format'] = $val;
                    }
                    // 4. Finish (e.g. "CP/CP")
                    elseif (preg_match('/^[A-Z]{2,3}\/[A-Z]{2,3}$/', strtoupper($val))) {
                        $pairs['finish'] = strtoupper($val);
                    }
                    // 5. Edge (e.g. "S2S")
                    elseif (in_array(strtoupper($val), ['S2S', 'R3/4', 'M4'])) {
                        $pairs['edge'] = strtoupper($val);
                    }
                    // 6. Length in mm (e.g. "400mm")
                    elseif (preg_match('/^\d{2,4}mm$/i', $val)) {
                        $pairs['length_mm'] = $val;
                    }
                    // 7. Weight (e.g. "70gr")
                    elseif (preg_match('/^\d+gr$/i', $val)) {
                        $pairs['weight'] = $val;
                    }
                    // 8. Packaging (e.g. "27 Ream", "6 Sheet")
                    elseif (preg_match('/^\d+\s*(Ream|Sheet|Box)$/i', $val)) {
                        $pairs['packaging'] = $val;
                    }
                    // 9. Brand/material name
                    elseif (!isset($pairs['brand']) && preg_match('/^[\p{L} \-]+$/u', $val)) {
                        $pairs['brand'] = $val;
                    }
                }

                // Optional fallback
                if (empty($pairs) && count($attrs) === 1) {
                    $pairs['brand'] = $attrs[0];
                }

                foreach ($pairs as $k => $v) {
                    DB::table('product_attributes')->insert([
                        'product_id' => $p->id,
                        'key'   => $k,
                        'value' => $v,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if (($idx = array_search($v, $toKeep, true)) !== false) {
                        unset($toKeep[$idx]);
                    }
                }
        };

        $this->info('Product attributes migration complete.');
    }
}
