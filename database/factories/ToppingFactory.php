<?php

namespace Database\Factories;

use App\Models\Topping;
use Illuminate\Database\Eloquent\Factories\Factory;

class ToppingFactory extends Factory
{
    protected $model = Topping::class;

    public function definition(): array
    {
        // Prefer your known list; fall back to faker food terms for variety
        static $known = [
            "2 Beef Patties","American Cheese","Avocado","Bacon","BBQ Sauce","Beef Patty",
            "Blue Cheese Crumbles","Caramelized Onions","Cheddar Cheese","Chipotle Mayo",
            "Grilled Mushrooms","Jalapeños","Ketchup","Lettuce","Mustard","Onion",
            "Onion Rings","Pepper Jack Cheese","Pickles","Quarter Pound Beef Patty",
            "Swiss Cheese","Tomato","Veggie Patty",
        ];

        $name = $this->faker->unique()->randomElement($known);

        return [
            'name' => $name,
        ];
    }
}
