<?php
// database/seeders/ToppingPolicySeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Topping;
use Illuminate\Support\Arr;

class ToppingPolicySeeder extends Seeder
{
    public function run(): void
    {
        $t = config('menu.toppings', []);
        $syn = $t['synonyms'] ?? [];

        $rows = [];
        foreach (['burger','side','drink'] as $cat) {
            foreach (($t[$cat] ?? []) as $name) {
                $name = (string)$name;
                $rows[$name] = $rows[$name] ?? ['name'=>$name, 'allowed_for'=>[], 'synonyms'=>($syn[$name] ?? [])];
                $rows[$name]['allowed_for'][] = $cat;
                $rows[$name]['allowed_for'] = array_values(array_unique($rows[$name]['allowed_for']));
                $rows[$name]['synonyms']     = array_values(array_unique($rows[$name]['synonyms']));
            }
        }

        foreach ($rows as $name => $data) {
            Topping::updateOrCreate(
                ['name' => $name],
                ['allowed_for' => $data['allowed_for'], 'synonyms' => $data['synonyms']]
            );
        }
    }
}
