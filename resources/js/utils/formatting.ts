export function formatPrice(amount: number): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}

export function generateLineKey(item: {
  id: number
  size?: string | null
  add?: string[] | null
  remove?: string[] | null
}): string {
  const addStr = (item.add ?? []).slice().sort().join('|')
  const removeStr = (item.remove ?? []).slice().sort().join('|')
  return `${item.id}-${item.size ?? 'none'}-${addStr}-${removeStr}`
}
