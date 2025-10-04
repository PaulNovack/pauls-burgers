export type Topping = string

export interface MenuItem {
  id: number
  name: string
  type: 'burger' | 'side' | 'drink' | string
  category?: string | null
  toppings?: Topping[] | null
  size: string | null
  price: number
}

export interface OrderItem extends MenuItem {
  quantity: number
  remove: Topping[] | null
  add: Topping[] | null
}

export interface MenuVariant {
  id: number
  size: string | null
  price: number
}

export interface MenuGroup {
  name: string
  variants: MenuVariant[]
}

export interface OrderSummary {
  items: OrderItem[]
  subtotal: number
  tax: number
  total: number
}

export interface ApiResponse<T> {
  items?: T
  message?: string
  error?: string
}
