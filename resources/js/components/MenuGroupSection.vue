<template>
  <section>
    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" :class="headerClass">
      <h2 class="text-xl font-semibold font-fredoka">
        <u>{{ title }}</u>
      </h2>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1">
      <article
        v-for="group in groups"
        :key="group.name"
        class="pt-2"
      >
        <div class="flex items-start justify-between gap-3">
          <h3 class="text-md font-semibold leading-snug font-fredoka">
            {{ group.name }}
          </h3>
          <div class="flex gap-2 flex-wrap">
            <div
              v-for="variant in group.variants"
              :key="variant.id"
              class="flex items-center gap-1"
            >
              <div class="text-xs">#{{ variant.id }} {{ variant.size }}</div>
              <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800">
                {{ formatPrice(variant.price) }}
              </span>
            </div>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { MenuGroup } from '@/types/menu'
import { formatPrice } from '@/utils/formatting'

interface Props {
  title: string
  groups: MenuGroup[]
  headerClass?: string
}

withDefaults(defineProps<Props>(), {
  headerClass: '',
})
</script>
