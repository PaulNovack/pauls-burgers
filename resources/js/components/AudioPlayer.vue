<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'

type PlayOptions = {
    volume?: number
    startAt?: number
}

const audioEl = ref<HTMLAudioElement | null>(null)
const src = ref<string>('')
const isPlaying = ref<boolean>(false)
const isReady = ref<boolean>(false)

const emit = defineEmits<{
    (e: 'ready'): void
    (e: 'started', payload: { url: string; startAt: number }): void
    (e: 'ended'): void
    (e: 'error', err: unknown): void
    (e: 'timeupdate', currentTime: number): void
    (e: 'progress'): void
}>()

// Keep handler refs so we can remove them on unmount
function onPlay() { isPlaying.value = true }
function onPause() { isPlaying.value = false }
function onEnded() { isPlaying.value = false; emit('ended') }
function onTimeUpdate() { if (audioEl.value) emit('timeupdate', audioEl.value.currentTime) }
function onProgress() { emit('progress') }
function onCanPlay() { isReady.value = true; emit('ready') }
function onError(e: Event) {
    isPlaying.value = false
    const mediaErr = audioEl.value?.error
    const err = mediaErr
        ? new Error(`Audio error (code ${mediaErr.code})`)
        : (e instanceof ErrorEvent ? e.error : new Error('Audio error'))
    emit('error', err)
}

function attachEvents() {
    const el = audioEl.value
    if (!el) return
    el.addEventListener('play', onPlay)
    el.addEventListener('pause', onPause)
    el.addEventListener('ended', onEnded)
    el.addEventListener('timeupdate', onTimeUpdate)
    el.addEventListener('progress', onProgress)
    el.addEventListener('canplay', onCanPlay)
    el.addEventListener('error', onError)
}

function detachEvents() {
    const el = audioEl.value
    if (!el) return
    el.removeEventListener('play', onPlay)
    el.removeEventListener('pause', onPause)
    el.removeEventListener('ended', onEnded)
    el.removeEventListener('timeupdate', onTimeUpdate)
    el.removeEventListener('progress', onProgress)
    el.removeEventListener('canplay', onCanPlay)
    el.removeEventListener('error', onError)
}

/** Imperative API exposed to parent via template ref */
async function play(url: string, { volume = 1.0, startAt = 0 }: PlayOptions = {}): Promise<void> {
    if (!url) return
    const sameSrc = src.value === url
    if (!sameSrc) {
        isReady.value = false
        src.value = url
    }

    await nextTick()
    const el = audioEl.value
    if (!el) return

    try {
        el.volume = Math.min(1, Math.max(0, volume))
        if (!sameSrc) el.load()
        if (startAt) el.currentTime = startAt
        const maybePromise = el.play()
        if (maybePromise && typeof (maybePromise as Promise<void>).then === 'function') {
            await (maybePromise as Promise<void>)
        }
        emit('started', { url, startAt })
    } catch (err) {
        emit('error', err)
    }
}

function pause(): void { audioEl.value?.pause() }
function stop(): void {
    const el = audioEl.value
    if (!el) return
    el.pause()
    el.currentTime = 0
}
function setVolume(v: number): void {
    const el = audioEl.value
    if (!el) return
    el.volume = Math.min(1, Math.max(0, v))
}

defineExpose({ play, pause, stop, setVolume, isPlaying, isReady })

onMounted(attachEvents)
onBeforeUnmount(detachEvents)
</script>

<template>
    <!-- Add `controls` if you want visible native controls -->
    <audio ref="audioEl" :src="src" preload="auto"></audio>
</template>

<style scoped>
/* Hide the native element by default; remove if you want visible controls */
audio { display: none; }
</style>
