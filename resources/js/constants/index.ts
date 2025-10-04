export const TAX_RATE = 0.08
export const DEFAULT_AUDIO_VOLUME = 0.9

export const SIZE_ORDER: Record<string, number> = {
  Regular: 0,
  Large: 1,
}

export const API_ENDPOINTS = {
  MENU: '/api/menu',
  ORDER: '/order',
  ORDER_ASR: '/order/asr',
  ORDER_COMMAND: '/order/command',
} as const
