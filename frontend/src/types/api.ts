export type Money = string | number
export type ItemType = 'PRODUCT' | 'SERVICE'
export type UnitType = 'OUTLET' | 'TICKETING'
export interface Person {
  id: number
  name: string
  email: string
}
export interface UnitSummary {
  id: number
  code: string
  name: string
  type?: UnitType
  scanner_enabled?: boolean
}
export interface Role {
  id: number
  name: string
  slug: string
  description: string | null
  is_active: boolean
  permissions: string[]
}
export interface Permission {
  id: number
  name: string
  slug: string
  description: string | null
}
export interface User extends Person {
  is_active: boolean
  role: Pick<Role, 'id' | 'name' | 'slug'> | null
  operational_unit: UnitSummary | null
  created_at: string | null
  updated_at: string | null
}
export interface AuthUser extends Omit<User, 'created_at' | 'updated_at'> {
  permissions: string[]
}
export interface InventoryLocation {
  id: number
  code: string
  name: string
  type: 'WAREHOUSE' | 'UNIT'
  operational_unit_id?: number | null
  is_active?: boolean
}
export interface OperationalUnit extends UnitSummary {
  type: UnitType
  scanner_enabled: boolean
  is_active: boolean
  inventory_location?: InventoryLocation | null
  created_at: string | null
  updated_at: string | null
}
export interface ItemSummary {
  id: number
  item_code: string
  barcode: string | null
  name: string
  type: ItemType
  uom?: string | null
  is_active?: boolean
  visitor_factor?: number
}
export interface Item extends ItemSummary {
  uom: string | null
  visitor_factor: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}
export interface UnitItem {
  id: number
  operational_unit: UnitSummary
  item: ItemSummary
  selling_price: Money
  minimum_stock: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}
export interface InventoryBalance {
  item: ItemSummary
  quantity_on_hand: number
  quantity_reserved: number
  available_quantity: number
  updated_at: string | null
  unit?: UnitSummary
  selling_price?: Money
  minimum_stock?: number
  low_stock?: boolean
}
export interface StockMovement {
  id: number
  location: InventoryLocation
  item: Pick<ItemSummary, 'id' | 'item_code' | 'barcode' | 'name'>
  direction: 'IN' | 'OUT'
  movement_type:
    | 'STOCK_IN'
    | 'STOCK_OUT'
    | 'TRANSFER_IN'
    | 'TRANSFER_OUT'
    | 'SALE'
  quantity: number
  performer: Person
  note: string | null
  reference_type: string | null
  reference_id: number | null
  created_at: string | null
}
export interface StockTransferItem {
  item: Pick<ItemSummary, 'id' | 'item_code' | 'barcode' | 'name' | 'uom'>
  quantity: number
}
export interface StockTransfer {
  id: number
  transfer_number: string
  status: 'PENDING' | 'ACCEPTED' | 'REJECTED'
  source_location: InventoryLocation
  destination: {
    operational_unit: UnitSummary | null
    location: InventoryLocation
  }
  creator: Person
  responder: Person | null
  rejection_reason: string | null
  created_at: string | null
  responded_at: string | null
  items?: StockTransferItem[]
}
export interface TransactionItem {
  id: number
  item: ItemSummary
  quantity: number
  unit_price: Money
  subtotal: Money
  input_method: 'MANUAL' | 'SCANNER'
}
export interface Transaction {
  id: number
  transaction_number: string
  operational_unit: UnitSummary
  status: 'CART' | 'PAID'
  creator: Person
  payer: Person | null
  subtotal: Money
  total: Money
  visitor_count: number
  created_at: string | null
  paid_at: string | null
  items?: TransactionItem[]
}
export interface CatalogItem {
  id: number
  operational_unit: UnitSummary
  item: ItemSummary
  selling_price: Money
  minimum_stock: number
  inventory: Pick<
    InventoryBalance,
    | 'quantity_on_hand'
    | 'quantity_reserved'
    | 'available_quantity'
    | 'low_stock'
  > | null
}
export interface ActivityLog {
  id: number
  actor: Person | null
  action: string
  subject_type: string | null
  subject_id: number | null
  metadata: unknown
  created_at: string | null
}
export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}
export interface Page<T> {
  data: T[]
  meta: PageMeta
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}
export interface Resource<T> {
  data: T
}
export type QueryParams = Record<string, string | number | boolean | undefined>
