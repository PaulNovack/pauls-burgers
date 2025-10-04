<template>
  <div class="grid grid-cols-[64px_1fr_auto] gap-3 items-center">
    <!-- Order lines -->
    <template v-if="items.length">
      <template v-for="line in items" :key="lineKey(line)">
        <div class="text-sm text-right">({{ line.quantity }})</div>

        <div class="text-sm text-left leading-tight">
          #{{ line.id }} {{ line.name }}
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

    <!-- Empty state -->
    <div v-else class="col-span-3 text-sm text-gray-600 py-4">
      <slot name="empty">
        <div>Press the Record button to start</div>
        <div>&nbsp;</div>
        <div>Say something like:</div>
        <div>&nbsp;</div>
        <div><em>"add a #3 with ketchup and mustard, without onions"</em></div>
        <div><em>"add a #5 without onion rings"</em></div>
        <div><em>"add a Barbecue Burger without onion rings"</em></div>
        <div><em>"add a large Fries with Ketchup"</em></div>
        <div><em>"Remove cheeseburger"</em></div>
        <div>&nbsp;</div>
        <div><em>"Clear Order"</em> or press clear button to start over</div>
      </slot>
    </div>

    <!-- Totals -->
    <div class="col-span-3 border-t border-gray-900 my-2"></div>

    <div></div>
    <div class="text-sm text-gray-600 text-right">Subtotal</div>
    <div class="text-sm">{{ formatPrice(subtotal) }}</div>

    <div></div>
    <div class="text-sm text-gray-600 text-right">Tax ({{ taxRate * 100 }}%)</div>
    <div class="text-sm text-right">{{ formatPrice(tax) }}</div>

    <div class="col-span-3 border-t border-gray-900 my-2"></div>

    <div></div>
    <div class="text-sm font-semibold text-right">Total</div>
    <div class="text-sm font-semibold text-right">{{ formatPrice(total) }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { OrderItem } from '@/types/menu'
import { formatPrice, generateLineKey } from '@/utils/formatting'
import { TAX_RATE } from '@/constants'

interface Props {
  items: OrderItem[]
  taxRate?: number
}

const props = withDefaults(defineProps<Props>(), {
  taxRate: TAX_RATE,
})

const subtotal = computed(() =>
  props.items.reduce((sum, line) => sum + line.price * (line.quantity || 0), 0)
)

const tax = computed(() => subtotal.value * props.taxRate)

const total = computed(() => subtotal.value + tax.value)

const lineKey = (line: OrderItem) => generateLineKey(line)
</script>
