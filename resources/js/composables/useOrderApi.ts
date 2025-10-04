import { ref } from 'vue'
import type { MenuItem, OrderItem, ApiResponse } from '@/types/menu'
import { API_ENDPOINTS } from '@/constants'

export function useOrderApi() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  function getCsrfHeader(): Record<string, string> {
    const headers: Record<string, string> = {}
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    if (meta?.content) {
      headers['X-CSRF-TOKEN'] = meta.content
    } else {
      const cookie = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))
      if (cookie) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(cookie.split('=')[1])
      }
    }
    return headers
  }

  async function loadMenu(): Promise<MenuItem[]> {
    loading.value = true
    error.value = null
    
    try {
      const res = await fetch(API_ENDPOINTS.MENU, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      
      if (!res.ok) {
        throw new Error(`Failed to load menu: HTTP ${res.status}`)
      }
      
      const json = await res.json() as ApiResponse<Record<string, any> | any[]>
      
      // Handle both shapes (array or object map)
      const incoming = Array.isArray(json.items)
        ? json.items
        : Object.values(json.items ?? {})
      
      return incoming.map((i: any) => ({
        id: Number(i.id),
        name: String(i.name ?? '').trim(),
        type: String(i.type ?? '').trim().toLowerCase(),
        category: i.category ?? null,
        toppings: Array.isArray(i.toppings) ? i.toppings : [],
        size: i.size ?? null,
        price: Number(i.price ?? 0),
      }))
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load menu'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function loadOrder(): Promise<OrderItem[]> {
    loading.value = true
    error.value = null
    
    try {
      const res = await fetch(API_ENDPOINTS.ORDER, {
        method: 'GET',
        headers: { Accept: 'application/json' },
        credentials: 'include',
      })
      
      if (!res.ok) {
        throw new Error(`Failed to load order: HTTP ${res.status}`)
      }
      
      const json = await res.json() as ApiResponse<OrderItem[] | Record<string, OrderItem>>
      
      return Array.isArray(json.items)
        ? json.items
        : Object.values(json.items ?? {})
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load order'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function sendTextCommand(text: string): Promise<OrderItem[]> {
    loading.value = true
    error.value = null
    
    try {
      const res = await fetch(API_ENDPOINTS.ORDER_COMMAND, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...getCsrfHeader(),
        },
        credentials: 'include',
        body: JSON.stringify({ text }),
      })
      
      if (!res.ok) {
        throw new Error(`Command failed: HTTP ${res.status}`)
      }
      
      const json = await res.json() as ApiResponse<OrderItem[]>
      return json.items ?? []
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to send command'
      throw e
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    loadMenu,
    loadOrder,
    sendTextCommand,
  }
}
