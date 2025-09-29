<template>
    <div class="flex flex-1 bg-orange-50">
        <!-- Left Column (2/5) -->
        <div class="w-4/14 p-2 font-fredoka text-xl text-center bg-cyan-100">
            <div class="p-4">
                <h2 class="text-xl mb-3 border-b-2">Your Order</h2>

                <div class="grid grid-cols-[64px_1fr_auto] gap-3 items-center">


                    <!-- lines -->
                    <template v-for="line in myOrder" :key="line.id">
                        ({{ line.quantity }})

                        <div class="text-md text-left leading-tight">
                            {{ line.name }}
                            <span v-if="line.size">({{ line.size }})</span>

                            <!-- Add / Without line -->
                            <div
                                v-if="(line.add && line.add.length) || (line.remove && line.remove.length)"
                                class="mt-1 text-xs"
                            >
                              <span v-if="line.add && line.add.length">
                                Add: {{ (line.add || []).join(', ') }}
                              </span>
                                <span v-if="line.remove && line.remove.length">
                                <template v-if="line.add && line.add.length"> • </template>
                                Without: {{ (line.remove || []).join(', ') }}
                              </span>
                            </div>
                        </div>

                        <div class="text-sm text-right font-medium">
                            {{ formatPrice(line.price * line.quantity) }}
                        </div>
                    </template>

                    <!-- totals -->
                    <div class="col-span-3 border-t border-gray-900 dark:border-gray-900 my-2"></div>

                    <div></div>
                    <div class="text-md text-gray-600 text-right">Subtotal</div>
                    <div class="text-md">{{ formatPrice(subtotal) }}</div>

                    <div></div>
                    <div class="text-md text-gray-600 text-right">Tax (8%)</div>
                    <div class="text-md text-right">{{ formatPrice(tax) }}</div>

                    <div class="col-span-3 border-t border-gray-900 dark:border-gray-800 my-2"></div>

                    <div></div>
                    <div class="text-md font-semibold text-right">Total</div>
                    <div class="text-md  font-semibold text-right">{{ formatPrice(total) }}</div>
                </div>
            </div>
        </div>


        <!-- Right Column (5/14) -->
        <div class="w-5/14 pt-2 px-2">
            <section >
                <!-- Header / Search -->
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Burgers</u>
                    </h2>

                </header>

                <!-- Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-1">
                    <article
                        v-for="item in burgers"
                        :key="item.id"
                        class="p-1"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-md font-semibold leading-snug font-fredoka">#{{item.id}} {{ item.name }}</h3>
                            <div class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800">
                                {{ formatPrice(item.price) }}
                            </div>
                        </div>

                        <p
                            v-if="item.type === 'burger' && item.toppings?.length"
                            class="mt-2 text-sm text-gray-600 dark:text-gray-300"
                        >
                            <span class="text-sm">Toppings:</span>
                            {{ (item.toppings || []).join(', ') }}
                        </p>
                    </article>
                </div>

            </section>
        </div>
        <div class="w-5/14 p-2">
            <section >
                <!-- Header / Search -->
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Sides</u></h2>

                </header>

                <!-- Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1">
                    <article
                        v-for="group in groupedSides"
                        :key="group.name"
                        class="pt-2"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-md font-semibold leading-snug font-fredoka">{{ group.name }}</h3>
                            <div class="flex gap-2 flex-wrap">
                                <div
                                    v-for="v in group.variants"
                                    :key="v.id"
                                    class="flex items-center gap-1"
                                >
                                    <!-- plain text -->
                                    <div class="text-xs">#{{v.id}} {{ v.size }}</div>

                                    <!-- styled span just for the price -->
                                    <span
                                        class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800"
                                    >
                                      {{ formatPrice(v.price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
            <section >
                <!-- Header / Search -->
                <header class="flex flex-col pt-4 gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Drinks</u></h2>

                </header>

                <!-- Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1">
                    <article
                        v-for="group in groupedDrinks"
                        :key="group.name"
                        class="pt-2"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-md font-semibold leading-snug font-fredoka">{{ group.name }}</h3>
                            <div class="flex gap-2 flex-wrap">
                                <div
                                    v-for="v in group.variants"
                                    :key="v.id"
                                    class="flex items-center gap-1"
                                >
                                    <!-- plain text -->
                                    <div class="text-xs">#{{v.id}} {{ v.size }}</div>

                                    <!-- styled span just for the price -->
                                    <span
                                        class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800"
                                    >
                                      {{ formatPrice(v.price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>



</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import RecordButton from '@/components/RecordButton.vue' // the VAD button you already built


type Variant = { id: number; size: string | null; price: number }
type SideGroup = { name: string; variants: Variant[] }

const groupedDrinks = computed<SideGroup[]>(() => {
    const byName = new Map<string, SideGroup>()

    for (const it of drinks.value) {
        const name = it.name
        if (!byName.has(name)) {
            byName.set(name, { name, variants: [] })
        }
        byName.get(name)!.variants.push({
            id: it.id,
            size: it.size ?? 'Regular',
            price: it.price,
        })
    }

    // sort variants Regular → Large (then by price), and groups alphabetically
    const sizeOrder: Record<string, number> = { Regular: 0, Large: 1 }
    const groups = Array.from(byName.values()).map(g => ({
        ...g,
        variants: g.variants
            .sort((a, b) =>
                (sizeOrder[a.size ?? 'Regular'] ?? 99) - (sizeOrder[b.size ?? 'Regular'] ?? 99) ||
                a.price - b.price
            ),
    }))

    return groups.sort((a, b) => a.name.localeCompare(b.name))
})

const groupedSides = computed<SideGroup[]>(() => {
    const byName = new Map<string, SideGroup>()

    for (const it of sides.value) {
        const name = it.name
        if (!byName.has(name)) {
            byName.set(name, { name, variants: [] })
        }
        byName.get(name)!.variants.push({
            id: it.id,
            size: it.size ?? 'Regular',
            price: it.price,
        })
    }

    // sort variants Regular → Large (then by price), and groups alphabetically
    const sizeOrder: Record<string, number> = { Regular: 0, Large: 1 }
    const groups = Array.from(byName.values()).map(g => ({
        ...g,
        variants: g.variants
            .sort((a, b) =>
                (sizeOrder[a.size ?? 'Regular'] ?? 99) - (sizeOrder[b.size ?? 'Regular'] ?? 99) ||
                a.price - b.price
            ),
    }))

    return groups.sort((a, b) => a.name.localeCompare(b.name))
})

type Burger = {
    id: number
    name: string
    type: string
    category?: string | null
    toppings?: string[] | null
    size: string | null
    price: number
}
type burgerToppings = [
    topping: string
]

type OrderItem = Burger & {
    quantity: number
    remove: Topping[] | null
    add: Topping[] | null
}

const props = defineProps<{
    items?: Burger[]
}>()


type Topping = string;

const burgerToppings: Topping[] = [
    "2 Beef Patties","American Cheese","Avocado","Bacon","BBQ Sauce","Beef Patty",
    "Blue Cheese Crumbles","Caramelized Onions","Cheddar Cheese","Chipotle Mayo",
    "Grilled Mushrooms","Jalapeños","Ketchup","Lettuce","Mustard","Onion",
    "Onion Rings","Pepper Jack Cheese","Pickles","Quarter Pound Beef Patty",
    "Swiss Cheese","Tomato","Veggie Patty",
];

// Default data (your 13-item menu). Pass `:items="yourData"` to override.
const defaultItems: Burger[] = [
    { "id": 1, "name": "Classic Hamburger", "toppings": ["Beef Patty", "Lettuce", "Tomato", "Onion", "Pickles"], "price": 5.99, "type": "burger", "category": "food", "size": null },
    { "id": 2, "name": "Cheeseburger", "toppings": ["Beef Patty", "Cheddar Cheese", "Lettuce", "Tomato", "Onion", "Pickles"], "price": 6.49, "type": "burger", "category": "food", "size": null },
    { "id": 3, "name": "Bacon Burger", "toppings": ["Beef Patty", "Bacon", "Cheddar Cheese", "BBQ Sauce"], "price": 7.49, "type": "burger", "category": "food", "size": null },
    { "id": 4, "name": "Mushroom Swiss Burger", "toppings": ["Beef Patty", "Swiss Cheese", "Grilled Mushrooms"], "price": 7.29, "type": "burger", "category": "food", "size": null },
    { "id": 5, "name": "BBQ Burger", "toppings": ["Beef Patty", "Onion Rings", "BBQ Sauce", "Cheddar Cheese"], "price": 7.59, "type": "burger", "category": "food", "size": null },
    { "id": 6, "name": "Double Cheeseburger", "toppings": ["2 Beef Patties", "American Cheese", "Lettuce", "Tomato"], "price": 8.49, "type": "burger", "category": "food", "size": null },
    { "id": 7, "name": "Veggie Burger", "toppings": ["Veggie Patty", "Lettuce", "Tomato", "Onion", "Avocado"], "price": 6.99, "type": "burger", "category": "food", "size": null },
    { "id": 8, "name": "Spicy Jalapeño Burger", "toppings": ["Beef Patty", "Pepper Jack Cheese", "Jalapeños", "Chipotle Mayo"], "price": 7.19, "type": "burger", "category": "food", "size": null },
    { "id": 9, "name": "Blue Cheese Burger", "toppings": ["Beef Patty", "Blue Cheese Crumbles", "Caramelized Onions"], "price": 7.39, "type": "burger", "category": "food", "size": null },
    { "id": 10, "name": "Quarter Pound Burger", "toppings": ["Quarter Pound Beef Patty", "Lettuce", "Tomato", "Onion"], "price": 6.79, "type": "burger", "category": "food", "size": null },
    { "id": 11, "name": "BBQ Bacon Burger", "toppings": ["Beef Patty", "Bacon", "BBQ Sauce", "Cheddar Cheese"], "price": 7.79, "type": "burger", "category": "food", "size": null },
    { "id": 12, "name": "Classic Double", "toppings": ["2 Beef Patties", "Lettuce", "Tomato", "Pickles", "Onion"], "price": 8.19, "type": "burger", "category": "food", "size": null },

    { "id": 13, "name": "Chili Cheese Fries", "price": 5.49, "type": "side", "category": "food", "size": "Regular" },
    { "id": 14, "name": "Chili Cheese Fries", "price": 6.49, "type": "side", "category": "food", "size": "Large" },
    { "id": 15, "name": "Coleslaw", "price": 2.49, "type": "side", "category": "food", "size": "Regular" },
    { "id": 16, "name": "Coleslaw", "price": 3.49, "type": "side", "category": "food", "size": "Large" },
    { "id": 17, "name": "Curly Fries", "price": 3.49, "type": "side", "category": "food", "size": "Regular" },
    { "id": 18, "name": "Curly Fries", "price": 4.49, "type": "side", "category": "food", "size": "Large" },
    { "id": 19, "name": "French Fries", "price": 2.99, "type": "side", "category": "food", "size": "Regular" },
    { "id": 20, "name": "French Fries", "price": 3.99, "type": "side", "category": "food", "size": "Large" },
    { "id": 21, "name": "Garlic Parmesan Fries", "price": 4.49, "type": "side", "category": "food", "size": "Regular" },
    { "id": 22, "name": "Garlic Parmesan Fries", "price": 5.49, "type": "side", "category": "food", "size": "Large" },
    { "id": 23, "name": "Mac & Cheese Bites", "price": 4.29, "type": "side", "category": "food", "size": "Regular" },
    { "id": 24, "name": "Mac & Cheese Bites", "price": 5.29, "type": "side", "category": "food", "size": "Large" },
    { "id": 25, "name": "Mozzarella Sticks", "price": 4.99, "type": "side", "category": "food", "size": "Regular" },
    { "id": 26, "name": "Mozzarella Sticks", "price": 5.99, "type": "side", "category": "food", "size": "Large" },
    { "id": 27, "name": "Onion Rings", "price": 3.99, "type": "side", "category": "food", "size": "Regular" },
    { "id": 28, "name": "Onion Rings", "price": 4.99, "type": "side", "category": "food", "size": "Large" },
    { "id": 29, "name": "Pickle Chips", "price": 2.79, "type": "side", "category": "food", "size": "Regular" },
    { "id": 30, "name": "Pickle Chips", "price": 3.79, "type": "side", "category": "food", "size": "Large" },
    { "id": 31, "name": "Side Salad", "price": 3.49, "type": "side", "category": "food", "size": "Regular" },
    { "id": 32, "name": "Side Salad", "price": 4.49, "type": "side", "category": "food", "size": "Large" },
    { "id": 33, "name": "Sweet Potato Fries", "price": 3.99, "type": "side", "category": "food", "size": "Regular" },
    { "id": 34, "name": "Sweet Potato Fries", "price": 4.99, "type": "side", "category": "food", "size": "Large" },
    { "id": 35, "name": "Tater Tots", "price": 3.29, "type": "side", "category": "food", "size": "Regular" },
    { "id": 36, "name": "Tater Tots", "price": 4.29, "type": "side", "category": "food", "size": "Large" },

    { "id": 37, "name": "Chocolate Milkshake", "price": 3.49, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 38, "name": "Chocolate Milkshake", "price": 4.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 39, "name": "Coca-Cola", "price": 1.99, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 40, "name": "Coca-Cola", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 41, "name": "Diet Coke", "price": 1.99, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 42, "name": "Diet Coke", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 43, "name": "Iced Tea", "price": 1.79, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 44, "name": "Iced Tea", "price": 2.29, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 45, "name": "Lemonade", "price": 1.99, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 46, "name": "Lemonade", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 47, "name": "Root Beer", "price": 1.99, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 48, "name": "Root Beer", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 49, "name": "Sprite", "price": 1.99, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 50, "name": "Sprite", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 51, "name": "Vanilla Milkshake", "price": 3.49, "type": "drink", "category": "drink", "size": "Regular" },
    { "id": 52, "name": "Vanilla Milkshake", "price": 4.49, "type": "drink", "category": "drink", "size": "Large" }
]

const myOrder: OrderItem[] = [
    { "id": 3, "remove":['Ketchup','Mustard'], "add": null, "quantity": 2, "name": "Bacon Burger", "toppings": ["Beef Patty","Bacon","Cheddar Cheese","BBQ Sauce"], "price": 7.49, "type": "burger", "category": "food", "size": null },
    { "id": 20, "add": null, "remove": null, "quantity": 2, "name": "French Fries", "price": 3.99, "type": "side", "category": "food", "size": "Large" },
    { "id": 46, "add": null, "remove": null, "quantity": 1, "name": "Lemonade", "price": 2.49, "type": "drink", "category": "drink", "size": "Large" },
    { "id": 52, "add": null, "remove": null, "quantity": 1,"name": "Vanilla Milkshake", "price": 4.49, "type": "drink", "category": "drink", "size": "Large" },
]

// make quantities editable; default to 1
type OrderLine = Burger & { qty: number }
const lines = ref<OrderLine[]>(myOrder.map(i => ({ ...i, qty: 1 })))

const subtotal = computed(() =>
    lines.value.reduce((sum, l) => sum + l.price * (l.qty || 0), 0)
)

const taxRate = 0.08
const tax = computed(() => subtotal.value * taxRate)
const total = computed(() => subtotal.value + tax.value)


const data = computed<Burger[]>(() => props.items?.length ? props.items : defaultItems)

const q = ref('')

const burgers = computed(() => defaultItems.filter(i => i.type === 'burger'))
const sides   = computed(() => defaultItems.filter(i => i.type === 'side'))
const drinks  = computed(() => defaultItems.filter(i => i.type === 'drink'))
function formatPrice(n: number) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n)
}
</script>
