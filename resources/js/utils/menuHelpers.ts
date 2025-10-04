import type { MenuItem, MenuGroup } from '@/types/menu'
import { SIZE_ORDER } from '@/constants'

export function groupMenuItems(items: MenuItem[]): MenuGroup[] {
  const byName = new Map<string, MenuGroup>()
  
  for (const item of items) {
    if (!byName.has(item.name)) {
      byName.set(item.name, { name: item.name, variants: [] })
    }
    
    byName.get(item.name)!.variants.push({
      id: item.id,
      size: item.size ?? 'Regular',
      price: item.price,
    })
  }
  
  return Array.from(byName.values())
    .map(group => ({
      ...group,
      variants: group.variants.sort((a, b) => {
        const sizeA = SIZE_ORDER[a.size ?? 'Regular'] ?? 99
        const sizeB = SIZE_ORDER[b.size ?? 'Regular'] ?? 99
        return sizeA - sizeB || a.price - b.price
      }),
    }))
    .sort((a, b) => a.name.localeCompare(b.name))
}
