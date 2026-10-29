export type Money = string | number
export type ItemType = 'PRODUCT' | 'SERVICE'
export type UnitType = 'OUTLET' | 'TICKETING'
export type StockUrgency =
  | 'HEALTHY'
  | 'NEAR_MINIMUM'
  | 'BELOW_MINIMUM'
  | 'OUT_OF_STOCK'
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
export interface RoleAssignment {
  id: number
  is_active: boolean
  role: Pick<Role, 'id' | 'name' | 'slug'>
  operational_unit: UnitSummary | null
}
export interface User extends Person {
  username: string
  is_active: boolean
  assignments: RoleAssignment[]
  role: Pick<Role, 'id' | 'name' | 'slug'> | null
  operational_unit: UnitSummary | null
  created_at: string | null
  updated_at: string | null
}
export interface AuthUser extends Omit<User, 'created_at' | 'updated_at'> {
  active_assignment: RoleAssignment | null
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
  safety_stock_mode: 'MANUAL' | 'CALCULATED'
  target_service_level: number
  supplier_lead_time_days: number
  order_pack_size: number
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
  stock_urgency?: StockUrgency
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
  maximum_quantity?: number
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
  inventory: (Pick<
    InventoryBalance,
    | 'quantity_on_hand'
    | 'quantity_reserved'
    | 'available_quantity'
    | 'low_stock'
  > & { stock_urgency: StockUrgency }) | null
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
export type ForecastHorizon = 'next_week' | 'next_month' | 'custom'
export interface ForecastDataFreshness {
  status: 'fresh' | 'aging' | 'stale'
  age_days: number
  gap_weeks: number
  requires_warning: boolean
}
export interface ForecastOptionUnit extends UnitSummary {
  history_start: string
  history_end: string
  history_weeks: number
  product_count: number
  data_freshness: ForecastDataFreshness
}
export interface ForecastOptions {
  units: ForecastOptionUnit[]
  explanation_provider: 'none' | 'openai' | 'ollama'
  explanation_runtime: {
    status: 'disabled' | 'not_checked' | 'unavailable' | 'model_missing' | 'ready'
    model: string | null
  }
}
export interface ForecastExplanation {
  summary: string
  risks: string[]
  actions: string[]
}
export type ForecastDecisionStatus = 'PENDING' | 'APPROVED' | 'REJECTED'
export interface ForecastDecision {
  id: number
  status: Exclude<ForecastDecisionStatus, 'PENDING'>
  recommended_restock_snapshot: number
  final_restock_quantity: number | null
  reason: string | null
  decided_by: Person
  decided_at: string
}
export interface ForecastResult {
  id: number
  item: Pick<ItemSummary, 'id' | 'item_code' | 'name' | 'uom'>
  model:
    | 'lag_1'
    | 'moving_average_4'
    | 'seasonal_52'
    | 'seasonal_average_3'
  history_weeks: number
  predicted_units: number
  available_quantity: number
  minimum_stock: number
  safety_stock_mode: 'MANUAL' | 'CALCULATED'
  target_service_level: number
  calculated_safety_stock: number
  safety_stock: number
  supplier_lead_time_days: number
  order_pack_size: number
  incoming_quantity: number
  unrounded_restock: number
  recommended_restock: number
  decision_status: ForecastDecisionStatus
  final_restock_quantity: number | null
  decision_reason: string | null
  decided_by: Person | null
  decided_at: string | null
  decision_history: ForecastDecision[]
  order_by_date: string | null
  order_status: 'not_required' | 'overdue' | 'due_today' | 'planned' | 'unknown'
  mae: number
  rmse: number
  wape: number
}
export interface ForecastRun {
  id: number
  operational_unit: UnitSummary
  requester: Person
  horizon: ForecastHorizon
  target_date: string
  forecast_start: string
  forecast_end: string
  history_through: string
  data_freshness: ForecastDataFreshness
  forecast_weeks: number
  method: string
  metrics: {
    mae: number
    rmse: number
    wape: number
    evaluation_weeks: number
    model_counts: Record<string, number>
    candidate_metrics?: Record<
      string,
      { mae: number; rmse: number; wape: number; observations: number }
    >
  }
  explanation_provider: 'none' | 'openai' | 'ollama'
  explanation_status: 'disabled' | 'pending' | 'generated' | 'fallback'
  explanation: ForecastExplanation | null
  created_at: string | null
  results: ForecastResult[]
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
