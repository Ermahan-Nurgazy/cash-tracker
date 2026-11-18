/** Ответ GET /api/v1/health */
export interface HealthResponse {
  status: 'ok' | 'degraded'
  database: 'up' | 'down'
  time: string
}

/** Суммы приходят строками ("1234.50"), чтобы не терять точность. Складывать суммы разных валют нельзя. */
export type Money = string

export type TransactionType = 'expense' | 'income'

export interface Paginated<T> {
  items: T[]
  total: number
  page: number
  pageCount: number
  perPage: number
}

// --- Аутентификация ---

export interface User {
  id: number
  email: string
  created_at: number
  updated_at: number
}

export interface Credentials {
  email: string
  password: string
}

export interface AuthResponse {
  user: User
  access_token: string
}

// --- Счета ---

export interface Account {
  id: number
  name: string
  currency: string
  initial_balance: Money
  created_at: number
  updated_at: number
}

export interface AccountInput {
  name: string
  currency: string
  initial_balance?: Money
}

// --- Категории ---

export interface Category {
  id: number
  name: string
  type: TransactionType
  color: string | null
  icon: string | null
  created_at: number
}

export interface CategoryInput {
  name: string
  type: TransactionType
  color?: string | null
  icon?: string | null
}

// --- Операции ---

export interface Transaction {
  id: number
  account_id: number
  category_id: number
  amount: Money
  type: TransactionType
  /** Дата в формате ГГГГ-ММ-ДД */
  occurred_at: string
  note: string | null
  created_at: number
  updated_at: number
  account: Pick<Account, 'id' | 'name' | 'currency'>
  category: Pick<Category, 'id' | 'name' | 'type' | 'color' | 'icon'>
}

export interface TransactionInput {
  account_id: number
  category_id: number
  amount: Money
  type: TransactionType
  occurred_at: string
  note?: string | null
}

export interface TransactionFilters {
  date_from?: string
  date_to?: string
  account_id?: number
  category_id?: number
  type?: TransactionType
  q?: string
  sort?: 'occurred_at' | '-occurred_at' | 'amount' | '-amount' | 'created_at' | '-created_at'
  page?: number
  'per-page'?: number
}

// --- Отчёты (в каждом блок на валюту, без конвертации) ---

export interface ReportPeriod {
  date_from: string
  date_to: string
}

export interface CategoryReportItem {
  category_id: number
  name: string
  color: string | null
  icon: string | null
  total: Money
  count: number
  /** Доля в итоге валюты, % */
  share: number
}

export interface CategoryReportCurrency {
  currency: string
  total: Money
  count: number
  categories: CategoryReportItem[]
}

export interface CategoryReport {
  type: TransactionType
  period: ReportPeriod
  currencies: CategoryReportCurrency[]
}

export interface MonthlyReportRow {
  /** Месяц в формате ГГГГ-ММ */
  month: string
  income: Money
  expense: Money
  balance: Money
}

export interface MonthlyReportCurrency {
  currency: string
  total_income: Money
  total_expense: Money
  balance: Money
  months: MonthlyReportRow[]
}

export interface MonthlyReport {
  period: ReportPeriod
  currencies: MonthlyReportCurrency[]
}

export interface AccountBalance {
  id: number
  name: string
  currency: string
  initial_balance: Money
  income: Money
  expense: Money
  balance: Money
}

export interface BalancesReport {
  accounts: AccountBalance[]
  totals: { currency: string; balance: Money }[]
}

export interface ReportParams {
  date_from?: string
  date_to?: string
  account_id?: number
}

// --- Бюджеты ---

export type BudgetStatus = 'ok' | 'warning' | 'exceeded'

export interface Budget {
  id: number
  category_id: number
  /** Месяц в формате ГГГГ-ММ */
  month: string
  currency: string
  limit: Money
  /** Потрачено в валюте бюджета */
  spent: Money
  /** Лимит минус потрачено; при превышении отрицательный */
  remaining: Money
  /** Целые проценты лимита, при превышении больше 100 */
  percent: number
  status: BudgetStatus
  category: Pick<Category, 'id' | 'name' | 'color' | 'icon'>
  created_at: number
  updated_at: number
}

export interface BudgetInput {
  category_id: number
  month: string
  currency: string
  limit: Money
}
