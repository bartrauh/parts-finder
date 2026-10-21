export interface Part {
  id: number
  name: string
  oem: string
  brand: string
  condition: 'new' | 'used'
  price: string
  quantity: number
  in_stock: boolean
  category: string
  image: string | null
  colours: string[]
}
