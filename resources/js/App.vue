<template>
    <div class="min-h-screen bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
        <NavBar />
        <main class="">
            <RouterView />
        </main>
    </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import NavBar from './components/NavBar.vue'
</script>
