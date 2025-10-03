<template>
    <button
        class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium text-white"
        :class="state === 'recording' ? 'bg-red-600' : 'bg-gray-900'"
        @click="toggle"
        :title="state === 'recording' ? 'Click to stop' : 'Click to start'"
    >
    <span class="tabular-nums">
      {{ state === 'recording' ? 'Stop' : 'Record' }}
    </span>

        <!-- VU meter -->
        <span class="ml-1 h-1 w-16 rounded bg-white/20 overflow-hidden">
      <span class="block h-full bg-white/80 transition-[width]"
            :style="{ width: Math.round(level * 100) + '%' }"></span>
    </span>

        <!-- Timer -->
        <span v-if="state === 'recording'" class="text-white/80 text-xs tabular-nums">
      {{ seconds }}s
    </span>

        <!-- Pending sends counter -->
        <span v-if="pendingSends > 0"
              class="ml-2 inline-flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-xs">
      <svg class="h-3 w-3 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"/>
      </svg>
      {{ pendingSends }}
    </span>
    </button>
</template>

<script setup lang="ts">
import {ref, onBeforeUnmount, onMounted} from 'vue'

/** ---------- Props & Emits ---------- */
const props = withDefaults(defineProps<{
    endpoint?: string
    filename?: string
    mimeHint?: string
    vad?: boolean
    silenceMs?: number      // silence gap to cut & send a segment
    maxSessionMs?: number   // optional max length of the entire session before auto-stop
    continuous?: boolean    // keep recording after each send
}>(), {
    endpoint: '/order/asr',
    filename: 'segment.webm',
    mimeHint: 'audio/webm;codecs=opus',
    vad: true,
    silenceMs: 1200,
    maxSessionMs: 0,
    continuous: true,
})

type OrderItem = {
    id: number; name: string; price: number; type: string;
    category?: string|null; size: string|null; toppings?: string[]|null;
    quantity: number; add: string[]|null; remove: string[]|null;
}

const emit = defineEmits<{
    (e: 'update:order', items: OrderItem[]): void
    (e: 'transcript', text: string): void
    (e: 'tts_transcript', text: string): void
    (e: 'state', state: 'idle'|'recording'|'error'): void
    (e: 'error', message: string): void
}>()

/** ---------- UI State ---------- */
const state = ref<'idle'|'recording'|'error'>('idle')
const level = ref(0)       // 0..1 VU
const seconds = ref(0)
const pendingSends = ref(0)

/** ---------- Media / VAD ---------- */
let stream: MediaStream | null = null
let rec: MediaRecorder | null = null
let chunks: BlobPart[] = []
let sessionStart = 0
let tickId: number | null = null

// Audio graph
let ctx: AudioContext | null = null
let source: MediaStreamAudioSourceNode | null = null
let analyser: AnalyserNode | null = null
let rafId: number | null = null
let lastSpeech = 0
let hadSpeechThisSegment = false
let sessionEnding = false

/** ---------- Public actions ---------- */
async function toggle() {
    if (state.value === 'recording') {
        await stopSession()   // end the whole session (but still send final segment if any)
    } else {
        await startSession()
    }
}

async function startSession() {
    try {
        sessionEnding = false
        stream = await navigator.mediaDevices.getUserMedia({ audio: true })
        await buildVad(stream)

        sessionStart = Date.now()
        seconds.value = 0
        tickId && clearInterval(tickId)
        tickId = window.setInterval(() => {
            seconds.value = Math.floor((Date.now() - sessionStart) / 1000)
            if (props.maxSessionMs && Date.now() - sessionStart > props.maxSessionMs) {
                stopSession()
            }
        }, 250) as unknown as number

        await startRecorder() // start first segment
        state.value = 'recording'
        emit('state', state.value)
    } catch (e: any) {
        fail(String(e?.message || e))
    }
}

async function stopSession() {
    try {
        sessionEnding = true
        // Stop recorder to flush last segment (if any)
        if (rec && rec.state === 'recording') rec.stop()
        // VAD loop will be stopped by cleanup after onstop
    } catch {}
}

/** ---------- Recorder segment control ---------- */
async function startRecorder() {
    const mime = pickMimeType(props.mimeHint)
    rec = new MediaRecorder(stream!, mime ? { mimeType: mime } : undefined)
    chunks = []
    hadSpeechThisSegment = false

    rec.ondataavailable = (ev) => {
        if (ev.data && ev.data.size > 0) chunks.push(ev.data)
    }

    rec.onstop = () => {
        const blob = new Blob(chunks, { type: rec?.mimeType || 'audio/webm' })
        chunks = []

        // Only send if we actually had speech & a non-empty blob
        if (blob.size > 0 && hadSpeechThisSegment) {
            void send(blob)
        }

        if (!sessionEnding && props.continuous && stream) {
            // immediately start next segment
            startRecorder()
        } else if (sessionEnding) {
            // done for real
            cleanupAll()
            state.value = 'idle'
            emit('state', state.value)
        }
    }

    rec.start(100) // collect 100ms chunks
}

/** ---------- Send to backend (CSRF-aware) ---------- */
function getCsrfHeader(): Record<string, string> {
    const headers: Record<string, string> = {}
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    if (meta?.content) {
        headers['X-CSRF-TOKEN'] = meta.content
    } else {
        // Sanctum fallback
        const cookie = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))
        if (cookie) headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.split('=')[1])
    }
    return headers
}

async function send(blob: Blob) {
    pendingSends.value++
    try {
        const fd = new FormData()
        fd.append('audio', blob, props.filename)

        const res = await fetch(props.endpoint, {
            method: 'POST',
            body: fd,
            headers: getCsrfHeader(),
            credentials: 'include', // keep Laravel session
        })

        if (!res.ok) throw new Error(`Server error (${res.status})`)
        const json = await res.json()

        if (json?.heard != null) emit('transcript', String(json.heard))
        if (json?.tts_url != null) emit('tts_transcript', String(json.tts_url))
        const items = Array.isArray(json?.items) ? json.items : Object.values(json?.items ?? {})
        emit('update:order', items as OrderItem[])
    } catch (e: any) {
        fail(String(e?.message || e))
    } finally {
        pendingSends.value--
    }
}

/** ---------- VAD (silence-based segmentation while recording) ---------- */
function pickMimeType(hint?: string): string | null {
    const candidates = [
        hint || '',
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/mp4',
    ].filter(Boolean)
    for (const c of candidates) {
        // @ts-ignore
        if (MediaRecorder.isTypeSupported?.(c)) return c
    }
    return null
}

async function buildVad(s: MediaStream) {
    ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
    source = ctx.createMediaStreamSource(s)
    analyser = ctx.createAnalyser()
    analyser.fftSize = 2048
    source.connect(analyser)

    const data = new Uint8Array(analyser.frequencyBinCount)
    lastSpeech = Date.now()
    hadSpeechThisSegment = false

    const loop = () => {
        if (!analyser) return
        analyser.getByteTimeDomainData(data)
        let sum = 0
        for (let i = 0; i < data.length; i++) {
            const v = (data[i] - 128) / 128
            sum += v * v
        }
        const rms = Math.sqrt(sum / data.length)
        level.value = Math.max(rms, level.value * 0.8)

        const speaking = rms > 0.05
        if (speaking) {
            lastSpeech = Date.now()
            hadSpeechThisSegment = true
        }

        if (props.vad && props.silenceMs && state.value === 'recording') {
            const silentLongEnough = Date.now() - lastSpeech > props.silenceMs
            // Cut a segment if we had speech and then silence
            if (silentLongEnough && hadSpeechThisSegment && rec?.state === 'recording') {
                try { rec.stop() } catch {}
                // onstop will send & restart (if not ending)
            }
        }

        rafId = requestAnimationFrame(loop)
    }
    rafId = requestAnimationFrame(loop)
}

/** ---------- Cleanup ---------- */
function cleanupAll() {
    try { tickId && clearInterval(tickId) } catch {}
    tickId = null

    if (rafId) cancelAnimationFrame(rafId)
    rafId = null

    try { source?.disconnect() } catch {}
    try { analyser?.disconnect() } catch {}
    try { ctx?.close() } catch {}

    source = null
    analyser = null
    ctx = null

    if (stream) {
        for (const t of stream.getTracks()) t.stop()
        stream = null
    }

    rec = null
    chunks = []
    hadSpeechThisSegment = false
}

function fail(msg: string) {
    console.error('[RecordButton]', msg)
    emit('error', msg)
    state.value = 'error'
    emit('state', state.value)
    cleanupAll()
}


onBeforeUnmount(() => {
    try { if (rec && rec.state === 'recording') rec.stop() } catch {}
    cleanupAll()
})
</script>
