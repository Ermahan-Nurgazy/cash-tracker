import type {
  BalancesReport,
  CategoryReport,
  MonthlyReport,
  ReportParams,
  TransactionType,
} from '@/types/api'
import { http } from './http'

export async function fetchCategoryReport(
  params: ReportParams & { type?: TransactionType } = {},
): Promise<CategoryReport> {
  const { data } = await http.get<CategoryReport>('/reports/categories', { params })
  return data
}

export async function fetchMonthlyReport(params: ReportParams = {}): Promise<MonthlyReport> {
  const { data } = await http.get<MonthlyReport>('/reports/monthly', { params })
  return data
}

export async function fetchBalances(): Promise<BalancesReport> {
  const { data } = await http.get<BalancesReport>('/reports/balances')
  return data
}
