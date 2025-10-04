<template>
  <div class="flex flex-1 bg-orange-50">
    <!-- Toast notifications -->
    <Toast
      :message="toastMessage"
      :type="toastType"
      @close="clearToast"
    />

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
            @tts_transcript="handleTTS"
          />
          <button
            class="text-xs px-3 py-1 rounded bg-gray-900 text-white hover:bg-gray-700 transition-colors"
            :disabled="loading"
            @click="clearOrder"
            title="Clear order in session"
            aria-label="Clear order"
          >
            Clear
          </button>
        </div>
        <div class="flex items-center justify-between mb-3">
          <button
            @click="playDing"
            class="px-3 py-2 rounded bg-gray-900 text-white text-sm hover:bg-gray-700 transition-colors"
            aria-label="Play test sound"
          >
            Play test sound
          </button>

          <AudioPlayer
            ref="player"
            @ready="() => console.log('buffered and ready')"
            @started="(info) => console.log('started', info)"
            @ended="() => console.log('ended')"
            @error="(e) => console.error('audio error', e)"
          />
        </div>

        <!-- Loading indicator -->
        <div v-if="loading" class="text-sm text-gray-600 py-2">
          <div class="animate-pulse">Loading...</div>
        </div>

        <!-- Order Summary -->
        <OrderSummary :items="myOrder" />
      </div>
    </div>

    <!-- Middle Column (Burgers) -->
    <div class="w-5/14 pt-2 px-2">
      <MenuBurgerSection
        title="Burgers"
        :items="burgers"
      />
    </div>

    <!-- Right Column (Sides + Drinks) -->
    <div class="w-5/14 p-2">
      <MenuGroupSection
        title="Sides"
        :groups="groupedSides"
      />

      <MenuGroupSection
        title="Drinks"
        :groups="groupedDrinks"
        header-class="pt-4"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import RecordButton from '@/components/RecordButton.vue'
import AudioPlayer from '@/components/AudioPlayer.vue'
import OrderSummary from '@/components/OrderSummary.vue'
import MenuBurgerSection from '@/components/MenuBurgerSection.vue'
import MenuGroupSection from '@/components/MenuGroupSection.vue'
import Toast from '@/components/Toast.vue'
import type { MenuItem, OrderItem } from '@/types/menu'
import { useOrderApi } from '@/composables/useOrderApi'
import { groupMenuItems } from '@/utils/menuHelpers'
import { DEFAULT_AUDIO_VOLUME } from '@/constants'

/** Audio Player Reference */
type AudioPlayerRef = {
  play: (url: string, opts?: { volume?: number; startAt?: number }) => Promise<void>
  pause: () => void
  stop: () => void
  setVolume: (v: number) => void
  isPlaying: boolean
  isReady: boolean
}

/** Component State */
const myOrder = ref<OrderItem[]>([])
const defaultItems = ref<MenuItem[]>([])
const player = ref<AudioPlayerRef | null>(null)
const toastMessage = ref<string | null>(null)
const toastType = ref<'error' | 'success' | 'info'>('info')

/** API Composable */
const { loading, error, loadMenu, loadOrder, sendTextCommand } = useOrderApi()

/** Computed Properties */
const burgers = computed(() => defaultItems.value.filter(i => i.type === 'burger'))
const sides = computed(() => defaultItems.value.filter(i => i.type === 'side'))
const drinks = computed(() => defaultItems.value.filter(i => i.type === 'drink'))
const groupedSides = computed(() => groupMenuItems(sides.value))
const groupedDrinks = computed(() => groupMenuItems(drinks.value))

/** Audio Functions */
function playDing() {
  player.value?.play('/wavs/newgreet.wav', { volume: DEFAULT_AUDIO_VOLUME })
    .catch((err) => {
      console.error('Failed to play sound:', err)
    })
}

function enableAudioOnce() {
  const handler = () => {
    playDing()
  }
  window.addEventListener('pointerdown', handler, { once: true, capture: true })
}

function playFromApi(url: string) {
  player.value?.play(url, { startAt: 0, volume: DEFAULT_AUDIO_VOLUME })
    .catch((err) => {
      console.error('Failed to play audio:', err)
      showToast('Failed to play audio', 'error')
    })
}

/** Order Management Functions */
async function fetchOrder() {
  try {
    playDing()
    const items = await loadOrder()
    myOrder.value = items
  } catch (err) {
    showToast(error.value || 'Failed to load order', 'error')
  }
}

async function fetchMenu() {
  try {
    const items = await loadMenu()
    defaultItems.value = items
  } catch (err) {
    showToast(error.value || 'Failed to load menu', 'error')
  }
}

async function clearOrder() {
  try {
    const items = await sendTextCommand('clear order')
    myOrder.value = items
    showToast('Order cleared successfully', 'success')
  } catch (err) {
    showToast(error.value || 'Failed to clear order', 'error')
  }
}

/** Event Handlers from RecordButton */
function handleUpdate(items: OrderItem[]) {
  myOrder.value = items
}

function handleTranscript(text: string) {
  console.log('Heard:', text)
  // Optional: could show in UI or as toast
}

function handleTTS(text: string) {
  console.log('Playing TTS:', text)
  playFromApi(text)
}

/** Toast Management */
function showToast(message: string, type: 'error' | 'success' | 'info' = 'info') {
  toastMessage.value = message
  toastType.value = type
  
  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    clearToast()
  }, 5000)
}

function clearToast() {
  toastMessage.value = null
}

/** Lifecycle Hooks */
onMounted(async () => {
  enableAudioOnce()
  await Promise.all([
    fetchOrder(),
    fetchMenu(),
  ])
})

onBeforeUnmount(() => {
  // Cleanup if needed
  player.value?.stop()
})
</script>
