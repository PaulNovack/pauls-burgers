<?php
// config/menu.php

return [

    // Sales tax used by UI totals, etc.
    'tax_rate' => 0.08, // 8%

    // Known toppings allow-list (for parsing "with/without ..." from ASR)
    'toppings' => [
        "2 Beef Patties",
        "American Cheese",
        "Avocado",
        "Bacon",
        "BBQ Sauce",
        "Beef Patty",
        "Blue Cheese Crumbles",
        "Caramelized Onions",
        "Cheddar Cheese",
        "Chipotle Mayo",
        "Grilled Mushrooms",
        "Jalapeños",
        "Ketchup",
        "Lettuce",
        "Mustard",
        "Onion",
        "Onion Rings",
        "Pepper Jack Cheese",
        "Pickles",
        "Quarter Pound Beef Patty",
        "Swiss Cheese",
        "Tomato",
        "Veggie Patty",
    ],

    // Catalog keyed by numeric ID for quick lookup
    'items' => [

        // ---------------- Burgers (1–12) ----------------
        1 => [
            'id' => 1, 'name' => 'Classic Hamburger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 5.99,
            'toppings' => ['Beef Patty','Lettuce','Tomato','Onion','Pickles'],
        ],
        2 => [
            'id' => 2, 'name' => 'Cheeseburger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 6.49,
            'toppings' => ['Beef Patty','Cheddar Cheese','Lettuce','Tomato','Onion','Pickles'],
        ],
        3 => [
            'id' => 3, 'name' => 'Bacon Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.49,
            'toppings' => ['Beef Patty','Bacon','Cheddar Cheese','BBQ Sauce'],
        ],
        4 => [
            'id' => 4, 'name' => 'Mushroom Swiss Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.29,
            'toppings' => ['Beef Patty','Swiss Cheese','Grilled Mushrooms'],
        ],
        5 => [
            'id' => 5, 'name' => 'BBQ Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.59,
            'toppings' => ['Beef Patty','Onion Rings','BBQ Sauce','Cheddar Cheese'],
        ],
        6 => [
            'id' => 6, 'name' => 'Double Cheeseburger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 8.49,
            'toppings' => ['2 Beef Patties','American Cheese','Lettuce','Tomato'],
        ],
        7 => [
            'id' => 7, 'name' => 'Veggie Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 6.99,
            'toppings' => ['Veggie Patty','Lettuce','Tomato','Onion','Avocado'],
        ],
        8 => [
            'id' => 8, 'name' => 'Spicy Jalapeño Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.19,
            'toppings' => ['Beef Patty','Pepper Jack Cheese','Jalapeños','Chipotle Mayo'],
        ],
        9 => [
            'id' => 9, 'name' => 'Blue Cheese Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.39,
            'toppings' => ['Beef Patty','Blue Cheese Crumbles','Caramelized Onions'],
        ],
        10 => [
            'id' => 10, 'name' => 'Quarter Pound Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 6.79,
            'toppings' => ['Quarter Pound Beef Patty','Lettuce','Tomato','Onion'],
        ],
        11 => [
            'id' => 11, 'name' => 'BBQ Bacon Burger', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 7.79,
            'toppings' => ['Beef Patty','Bacon','BBQ Sauce','Cheddar Cheese'],
        ],
        12 => [
            'id' => 12, 'name' => 'Classic Double', 'type' => 'burger', 'category' => 'food', 'size' => null, 'price' => 8.19,
            'toppings' => ['2 Beef Patties','Lettuce','Tomato','Pickles','Onion'],
        ],

        // ---------------- Sides (13–36) ----------------
        13 => ['id' => 13, 'name' => 'Chili Cheese Fries', 'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 5.49, 'toppings' => null],
        14 => ['id' => 14, 'name' => 'Chili Cheese Fries', 'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 6.49, 'toppings' => null],
        15 => ['id' => 15, 'name' => 'Coleslaw',            'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 2.49, 'toppings' => null],
        16 => ['id' => 16, 'name' => 'Coleslaw',            'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 3.49, 'toppings' => null],
        17 => ['id' => 17, 'name' => 'Curly Fries',         'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 3.49, 'toppings' => null],
        18 => ['id' => 18, 'name' => 'Curly Fries',         'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 4.49, 'toppings' => null],
        19 => ['id' => 19, 'name' => 'French Fries',        'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 2.99, 'toppings' => null],
        20 => ['id' => 20, 'name' => 'French Fries',        'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 3.99, 'toppings' => null],
        21 => ['id' => 21, 'name' => 'Garlic Parmesan Fries','type' => 'side','category' => 'food','size' => 'Regular','price' => 4.49,'toppings' => null],
        22 => ['id' => 22, 'name' => 'Garlic Parmesan Fries','type' => 'side','category' => 'food','size' => 'Large',  'price' => 5.49,'toppings' => null],
        23 => ['id' => 23, 'name' => 'Mac & Cheese Bites',  'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 4.29, 'toppings' => null],
        24 => ['id' => 24, 'name' => 'Mac & Cheese Bites',  'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 5.29, 'toppings' => null],
        25 => ['id' => 25, 'name' => 'Mozzarella Sticks',   'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 4.99, 'toppings' => null],
        26 => ['id' => 26, 'name' => 'Mozzarella Sticks',   'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 5.99, 'toppings' => null],
        27 => ['id' => 27, 'name' => 'Onion Rings',         'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 3.99, 'toppings' => null],
        28 => ['id' => 28, 'name' => 'Onion Rings',         'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 4.99, 'toppings' => null],
        29 => ['id' => 29, 'name' => 'Pickle Chips',        'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 2.79, 'toppings' => null],
        30 => ['id' => 30, 'name' => 'Pickle Chips',        'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 3.79, 'toppings' => null],
        31 => ['id' => 31, 'name' => 'Side Salad',          'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 3.49, 'toppings' => null],
        32 => ['id' => 32, 'name' => 'Side Salad',          'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 4.49, 'toppings' => null],
        33 => ['id' => 33, 'name' => 'Sweet Potato Fries',  'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 3.99, 'toppings' => null],
        34 => ['id' => 34, 'name' => 'Sweet Potato Fries',  'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 4.99, 'toppings' => null],
        35 => ['id' => 35, 'name' => 'Tater Tots',          'type' => 'side', 'category' => 'food', 'size' => 'Regular', 'price' => 3.29, 'toppings' => null],
        36 => ['id' => 36, 'name' => 'Tater Tots',          'type' => 'side', 'category' => 'food', 'size' => 'Large',   'price' => 4.29, 'toppings' => null],

        // ---------------- Drinks (37–52) ----------------
        37 => ['id' => 37, 'name' => 'Chocolate Milkshake', 'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 3.49, 'toppings' => null],
        38 => ['id' => 38, 'name' => 'Chocolate Milkshake', 'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 4.49, 'toppings' => null],
        39 => ['id' => 39, 'name' => 'Coca-Cola',           'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.99, 'toppings' => null],
        40 => ['id' => 40, 'name' => 'Coca-Cola',           'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.49, 'toppings' => null],
        41 => ['id' => 41, 'name' => 'Diet Coke',           'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.99, 'toppings' => null],
        42 => ['id' => 42, 'name' => 'Diet Coke',           'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.49, 'toppings' => null],
        43 => ['id' => 43, 'name' => 'Iced Tea',            'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.79, 'toppings' => null],
        44 => ['id' => 44, 'name' => 'Iced Tea',            'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.29, 'toppings' => null],
        45 => ['id' => 45, 'name' => 'Lemonade',            'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.99, 'toppings' => null],
        46 => ['id' => 46, 'name' => 'Lemonade',            'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.49, 'toppings' => null],
        47 => ['id' => 47, 'name' => 'Root Beer',           'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.99, 'toppings' => null],
        48 => ['id' => 48, 'name' => 'Root Beer',           'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.49, 'toppings' => null],
        49 => ['id' => 49, 'name' => 'Sprite',              'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 1.99, 'toppings' => null],
        50 => ['id' => 50, 'name' => 'Sprite',              'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 2.49, 'toppings' => null],
        51 => ['id' => 51, 'name' => 'Vanilla Milkshake',   'type' => 'drink', 'category' => 'drink', 'size' => 'Regular', 'price' => 3.49, 'toppings' => null],
        52 => ['id' => 52, 'name' => 'Vanilla Milkshake',   'type' => 'drink', 'category' => 'drink', 'size' => 'Large',   'price' => 4.49, 'toppings' => null],
    ],
];
