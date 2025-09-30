<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Topping;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        // Decide type first
        $type = $this->faker->randomElement(['burger', 'side', 'drink']);

        // Name pools to look realistic
        $burgerNames = [
            'Classic Hamburger',
            'Cheeseburger',
            'Bacon Burger',
            'Mushroom Swiss Burger',
            'BBQ Burger',
            'Double Cheeseburger',
            'Veggie Burger',
            'Spicy Jalapeño Burger',
            'Blue Cheese Burger',
            'Quarter Pound Burger',
            'BBQ Bacon Burger',
            'Classic Double',
        ];
        $sideNames = [
            'French Fries',
            'Curly Fries',
            'Onion Rings',
            'Mozzarella Sticks',
            'Chili Cheese Fries',
            'Side Salad',
            'Coleslaw',
            'Pickle Chips',
            'Garlic Parmesan Fries',
            'Sweet Potato Fries',
            'Tater Tots',
            'Mac & Cheese Bites',
        ];
        $drinkNames = [
            'Coca-Cola',
            'Diet Coke',
            'Sprite',
            'Root Beer',
            'Iced Tea',
            'Lemonade',
            'Chocolate Milkshake',
            'Vanilla Milkshake',
        ];

        $name = match ($type) {
            'burger' => $this->faker->randomElement($burgerNames),
            'side' => $this->faker->randomElement($sideNames),
            'drink' => $this->faker->randomElement($drinkNames),
        };

        // Size/category/price rules
        $size = $type === 'burger'
            ? null
            : $this->faker->randomElement(['Regular', 'Large']);

        $category = $type === 'drink' ? 'drink' : 'food';

        // Give a sane price range
        $price = match ($type) {
            'burger' => $this->faker->randomFloat(2, 5.50, 9.50),
            'side' => $this->faker->randomFloat(2, 2.25, 6.50),
            'drink' => $this->faker->randomFloat(2, 1.50, 4.75),
        };

        return [
            // Use unique random ids since your PK isn't auto-incrementing
            'id' => $this->faker->unique()->numberBetween(1, 9999),
            'name' => $name,
            'type' => $type,
            'category' => $category,
            'size' => $size,
            'price' => $price,
        ];
    }

    /** State helpers to force specific types/sizes/ranges (handy for tests/seeders) */

    public function burger(): self
    {
        return $this->state(function (array $attrs) {
            return [
                'type' => 'burger',
                'category' => 'food',
                'size' => null,
                'price' => $this->faker->randomFloat(2, 5.50, 9.50),
            ];
        });
    }

    public function sideRegular(): self
    {
        return $this->state(fn() => [
            'type' => 'side',
            'category' => 'food',
            'size' => 'Regular',
            'price' => $this->faker->randomFloat(2, 2.25, 5.50),
        ]);
    }

    public function sideLarge(): self
    {
        return $this->state(fn() => [
            'type' => 'side',
            'category' => 'food',
            'size' => 'Large',
            'price' => $this->faker->randomFloat(2, 3.00, 6.50),
        ]);
    }

    public function drinkRegular(): self
    {
        return $this->state(fn() => [
            'type' => 'drink',
            'category' => 'drink',
            'size' => 'Regular',
            'price' => $this->faker->randomFloat(2, 1.50, 3.50),
        ]);
    }

    public function drinkLarge(): self
    {
        return $this->state(fn() => [
            'type' => 'drink',
            'category' => 'drink',
            'size' => 'Large',
            'price' => $this->faker->randomFloat(2, 1.95, 4.75),
        ]);
    }

    /**
     * After creating an Item, auto-attach toppings for burgers.
     * If there are no toppings yet, it will create some via ToppingFactory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function (Item $item) {
            if ($item->type !== 'burger') {
                return;
            }

            $existing = Topping::query()->count();
            if ($existing === 0) {
                Topping::factory()->count(15)->create();
            }

            $count = $this->faker->numberBetween(2, 5);
            $toppingIds = Topping::inRandomOrder()->limit($count)->pluck('id')->all();
            $item->toppings()->sync($toppingIds);
        });
    }
}
