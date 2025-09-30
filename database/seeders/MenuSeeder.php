<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Topping;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = config('menu');

        DB::transaction(function () use ($menu) {
            // tax rate
            if (isset($menu['tax_rate'])) {
                Setting::updateOrCreate(['key' => 'tax_rate'], ['value' => (string)$menu['tax_rate']]);
            }

            // toppings
            $toppingIdsByName = [];
            foreach ($menu['toppings'] as $name) {
                $top = Topping::firstOrCreate(['name' => $name]);
                $toppingIdsByName[$name] = $top->id;
            }

            // items
            foreach ($menu['items'] as $m) {
                /** @var \App\Models\Item $item */
                $item = Item::updateOrCreate(
                    ['id' => (int)$m['id']],
                    [
                        'name'     => $m['name'],
                        'type'     => $m['type'],
                        'category' => $m['category'] ?? null,
                        'size'     => $m['size'] ?? null,
                        'price'    => $m['price'],
                    ]
                );

                // attach default toppings (burgers only)
                $item->toppings()->detach();
                if (!empty($m['toppings']) && is_array($m['toppings'])) {
                    $ids = [];
                    foreach ($m['toppings'] as $tName) {
                        // create on-the-fly if a burger lists a topping not in allow-list
                        if (!isset($toppingIdsByName[$tName])) {
                            $top = Topping::firstOrCreate(['name' => $tName]);
                            $toppingIdsByName[$tName] = $top->id;
                        }
                        $ids[] = $toppingIdsByName[$tName];
                    }
                    $item->toppings()->sync($ids);
                }
            }
        });
    }
}
