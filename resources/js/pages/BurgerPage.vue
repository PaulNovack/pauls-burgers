<template>
    <div class="flex flex-1 bg-orange-50">
        <!-- Left Column (Order) -->
        <div class="w-4/14 p-2 font-fredoka text-xl text-center bg-cyan-100">
            <div class="p-4">
                <h2 class="text-xl mb-3 border-b-2">Your Order</h2>

                <!-- Record / Controls -->
                <div class="flex items-center justify-between mb-3">
                    <RecordButton
                        endpoint="/order/asr"
                        @update:order="handleUpdate"
                        @transcript="handleTranscript"
                    />
                    <button
                        class="text-xs px-3 py-1 rounded bg-gray-900 text-white"
                        @click="sendTextCommand('clear order')"
                        title="Clear order in session"
                    >
                        Clear
                    </button>
                </div>

                <div class="grid grid-cols-[64px_1fr_auto] gap-3 items-center">
                    <!-- lines -->
                    <template v-if="myOrder.length">
                        <template v-for="line in myOrder" :key="lineKey(line)">
                            <div class="text-sm text-right">({{ line.quantity }})</div>

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
                    </template>

                    <div v-else class="col-span-3 text-sm text-gray-600 py-4">
                        Say something like: <em>“add a #3 with ketchup and mustard, without onions”</em> OR
                        <em>“add a #5 without onion rings”</em>
                        <em>“Clear Order”</em> to start over
                    </div>

                    <!-- totals -->
                    <div class="col-span-3 border-t border-gray-900 my-2"></div>

                    <div></div>
                    <div class="text-md text-gray-600 text-right">Subtotal</div>
                    <div class="text-md">{{ formatPrice(subtotal) }}</div>

                    <div></div>
                    <div class="text-md text-gray-600 text-right">Tax (8%)</div>
                    <div class="text-md text-right">{{ formatPrice(tax) }}</div>

                    <div class="col-span-3 border-t border-gray-900 my-2"></div>

                    <div></div>
                    <div class="text-md font-semibold text-right">Total</div>
                    <div class="text-md font-semibold text-right">{{ formatPrice(total) }}</div>
                </div>
            </div>
        </div>

        <!-- Middle Column (Burgers) -->
        <div class="w-5/14 pt-2 px-2">
            <section>
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Burgers</u></h2>
                </header>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-1">
                    <article
                        v-for="item in burgers"
                        :key="item.id"
                        class="p-1"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-md font-semibold leading-snug font-fredoka">#{{ item.id }} {{ item.name }}</h3>
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

        <!-- Right Column (Sides + Drinks) -->
        <div class="w-5/14 p-2">
            <section>
                <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Sides</u></h2>
                </header>

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
                                    <div class="text-xs">#{{ v.id }} {{ v.size }}</div>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800">
                    {{ formatPrice(v.price) }}
                  </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section>
                <header class="flex flex-col pt-4 gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-xl font-semibold font-fredoka"><u>Drinks</u></h2>
                </header>

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
                                    <div class="text-xs">#{{ v.id }} {{ v.size }}</div>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800">
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
import { ref, computed, onMounted } from 'vue'
import RecordButton from '@/components/RecordButton.vue'

/** ---------- Types ---------- */
type Topping = string

type Burger = {
    id: number
    name: string
    type: 'burger' | 'side' | 'drink' | string
    category?: string | null
    toppings?: string[] | null
    size: string | null
    price: number
}

type OrderItem = Burger & {
    quantity: number
    remove: Topping[] | null
    add: Topping[] | null
}

/** ---------- Order state (from backend session) ---------- */
const myOrder = ref<OrderItem[]>([])

// fetch session order when page loads
async function loadOrder() {
    const res = await fetch('/order', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'include',            // <- send/receive session cookie
    })
    const json = await res.json()
    myOrder.value = Array.isArray(json.items)
        ? json.items
        : Object.values(json.items ?? {})
}

onMounted(loadOrder)


/** Emit handlers from RecordButton */
function handleUpdate(items: OrderItem[]) {
    myOrder.value = items
}
function handleTranscript(text: string) {
    // Optional: show somewhere, or toast, etc.
    console.log('Heard:', text)
}

/** Optional text command helper (uses /order/command if you kept it) */
async function sendTextCommand(text: string) {
    const res = await fetch('/order/command', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...getCsrfHeader(),          // <<< add this
        },
        credentials: 'include',        // keep session cookie
        body: JSON.stringify({ text }),
    })
    const json = await res.json()
    handleUpdate(json.items ?? [])
}

function getCsrfHeader(): Record<string, string> {
    const headers: Record<string, string> = {}
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    if (meta?.content) headers['X-CSRF-TOKEN'] = meta.content
    else {
        const cookie = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))
        if (cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.split('=')[1])
    }
    return headers
}


/** A stable key for v-for combining id/size/add/remove */
function lineKey(l: OrderItem) {
    const a = (l.add ?? []).slice().sort().join('|')
    const r = (l.remove ?? []).slice().sort().join('|')
    return `${l.id}-${l.size ?? 'none'}-${a}-${r}`
}

/** ---------- Totals ---------- */
const subtotal = computed(() =>
    (myOrder.value ?? []).reduce((sum, l) => sum + l.price * (l.quantity || 0), 0)
)
const tax = computed(() => subtotal.value * 0.08)
const total = computed(() => subtotal.value + tax.value)

function formatPrice(n: number) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n)
}

/** ---------- Static catalog (shown on right) ---------- */
const defaultItems: Burger[] = [
    { "id": 1, "name": "Classic Hamburger", "toppings": ["Beef Patty","Lettuce","Tomato","Onion","Pickles"], "price": 5.99, "type": "burger", "category": "food", "size": null },
    { "id": 2, "name": "Cheeseburger", "toppings": ["Beef Patty","Cheddar Cheese","Lettuce","Tomato","Onion","Pickles"], "price": 6.49, "type": "burger", "category": "food", "size": null },
    { "id": 3, "name": "Bacon Burger", "toppings": ["Beef Patty","Bacon","Cheddar Cheese","BBQ Sauce"], "price": 7.49, "type": "burger", "category": "food", "size": null },
    { "id": 4, "name": "Mushroom Swiss Burger", "toppings": ["Beef Patty","Swiss Cheese","Grilled Mushrooms"], "price": 7.29, "type": "burger", "category": "food", "size": null },
    { "id": 5, "name": "BBQ Burger", "toppings": ["Beef Patty","Onion Rings","BBQ Sauce","Cheddar Cheese"], "price": 7.59, "type": "burger", "category": "food", "size": null },
    { "id": 6, "name": "Double Cheeseburger", "toppings": ["2 Beef Patties","American Cheese","Lettuce","Tomato"], "price": 8.49, "type": "burger", "category": "food", "size": null },
    { "id": 7, "name": "Veggie Burger", "toppings": ["Veggie Patty","Lettuce","Tomato","Onion","Avocado"], "price": 6.99, "type": "burger", "category": "food", "size": null },
    { "id": 8, "name": "Spicy Jalapeño Burger", "toppings": ["Beef Patty","Pepper Jack Cheese","Jalapeños","Chipotle Mayo"], "price": 7.19, "type": "burger", "category": "food", "size": null },
    { "id": 9, "name": "Blue Cheese Burger", "toppings": ["Beef Patty","Blue Cheese Crumbles","Caramelized Onions"], "price": 7.39, "type": "burger", "category": "food", "size": null },
    { "id": 10, "name": "Quarter Pound Burger", "toppings": ["Quarter Pound Beef Patty","Lettuce","Tomato","Onion"], "price": 6.79, "type": "burger", "category": "food", "size": null },
    { "id": 11, "name": "BBQ Bacon Burger", "toppings": ["Beef Patty","Bacon","BBQ Sauce","Cheddar Cheese"], "price": 7.79, "type": "burger", "category": "food", "size": null },
    { "id": 12, "name": "Classic Double", "toppings": ["2 Beef Patties","Lettuce","Tomato","Pickles","Onion"], "price": 8.19, "type": "burger", "category": "food", "size": null },

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
    { "id": 52, "name": "Vanilla Milkshake", "price": 4.49, "type": "drink", "category": "drink", "size": "Large" },
]

/** Derived groups for right column */
type Variant = { id: number; size: string | null; price: number }
type SideGroup = { name: string; variants: Variant[] }

const burgers = computed(() => defaultItems.filter(i => i.type === 'burger'))
const sides   = computed(() => defaultItems.filter(i => i.type === 'side'))
const drinks  = computed(() => defaultItems.filter(i => i.type === 'drink'))

const groupedSides = computed<SideGroup[]>(() => {
    const byName = new Map<string, SideGroup>()
    for (const it of sides.value) {
        if (!byName.has(it.name)) byName.set(it.name, { name: it.name, variants: [] })
        byName.get(it.name)!.variants.push({ id: it.id, size: it.size ?? 'Regular', price: it.price })
    }
    const sizeOrder: Record<string, number> = { Regular: 0, Large: 1 }
    return Array.from(byName.values())
        .map(g => ({ ...g, variants: g.variants.sort((a,b) =>
                (sizeOrder[a.size ?? 'Regular'] ?? 99) - (sizeOrder[b.size ?? 'Regular'] ?? 99) || a.price - b.price) }))
        .sort((a,b) => a.name.localeCompare(b.name))
})

const groupedDrinks = computed<SideGroup[]>(() => {
    const byName = new Map<string, SideGroup>()
    for (const it of drinks.value) {
        if (!byName.has(it.name)) byName.set(it.name, { name: it.name, variants: [] })
        byName.get(it.name)!.variants.push({ id: it.id, size: it.size ?? 'Regular', price: it.price })
    }
    const sizeOrder: Record<string, number> = { Regular: 0, Large: 1 }
    return Array.from(byName.values())
        .map(g => ({ ...g, variants: g.variants.sort((a,b) =>
                (sizeOrder[a.size ?? 'Regular'] ?? 99) - (sizeOrder[b.size ?? 'Regular'] ?? 99) || a.price - b.price) }))
        .sort((a,b) => a.name.localeCompare(b.name))
})
</script>
