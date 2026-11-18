export interface NavItem {
  name: string
  label: string
  icon: string
}

/** Разделы приложения: порядок совпадает с порядком в меню */
export const navItems: NavItem[] = [
  { name: 'dashboard', label: 'Дашборд', icon: 'pi pi-chart-pie' },
  { name: 'transactions', label: 'Операции', icon: 'pi pi-list' },
  { name: 'categories', label: 'Категории', icon: 'pi pi-tags' },
  { name: 'accounts', label: 'Счета', icon: 'pi pi-wallet' },
]
