<template>
  <section>
    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h2 class="text-xl font-semibold font-fredoka">
        <u>{{ title }}</u>
      </h2>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-1">
      <article
        v-for="item in items"
        :key="item.id"
        class="p-1"
      >
        <div class="flex items-start justify-between gap-3">
          <h3 class="text-md font-semibold leading-snug font-fredoka">
            #{{ item.id }} {{ item.name }}
          </h3>
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
</template>

<script setup lang="ts">
import type { MenuItem } from '@/types/menu'
import { formatPrice } from '@/utils/formatting'

interface Props {
  title: string
  items: MenuItem[]
}

defineProps<Props>()
</script>
