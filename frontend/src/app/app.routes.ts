import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { LoginComponent } from './features/auth/login/login.component';
import { ShellComponent } from './layout/shell/shell.component';
import { DashboardComponent } from './features/dashboard/dashboard.component';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  {
    path: '',
    component: ShellComponent,
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', component: DashboardComponent },

      // ── Master Data ──────────────────────────────────────────────────────
      { path: 'master/products',   loadComponent: () => import('./features/master/products/products-list.component').then(m => m.ProductsListComponent) },
      { path: 'master/categories', loadComponent: () => import('./features/master/categories/categories-list.component').then(m => m.CategoriesListComponent) },
      { path: 'master/units',      loadComponent: () => import('./features/master/units/units-list.component').then(m => m.UnitsListComponent) },
      { path: 'master/warehouses', loadComponent: () => import('./features/master/warehouses/warehouses-list.component').then(m => m.WarehousesListComponent) },
      { path: 'master/customers',  loadComponent: () => import('./features/master/customers/customers-list.component').then(m => m.CustomersListComponent) },
      { path: 'master/suppliers',  loadComponent: () => import('./features/master/suppliers/suppliers-list.component').then(m => m.SuppliersListComponent) },

      // ── Purchase ─────────────────────────────────────────────────────────
      { path: 'purchase/requisitions', loadComponent: () => import('./features/purchase/requisitions/requisitions-list.component').then(m => m.RequisitionsListComponent) },
      { path: 'purchase/orders',       loadComponent: () => import('./features/purchase/orders/orders-list.component').then(m => m.OrdersListComponent) },
      { path: 'purchase/grn',          loadComponent: () => import('./features/purchase/grn/grn-list.component').then(m => m.GrnListComponent) },
      { path: 'purchase/returns',      loadComponent: () => import('./features/purchase/returns/returns-list.component').then(m => m.ReturnsListComponent) },

      // ── Inventory ────────────────────────────────────────────────────────
      { path: 'inventory/balances',  loadComponent: () => import('./features/inventory/balances/stock-balances.component').then(m => m.StockBalancesComponent) },
      { path: 'inventory/ledger',    loadComponent: () => import('./features/inventory/ledger/stock-ledger.component').then(m => m.StockLedgerComponent) },
      { path: 'inventory/transfers', loadComponent: () => import('./features/inventory/transfers/transfers-list.component').then(m => m.TransfersListComponent) },

      // ── Sales ────────────────────────────────────────────────────────────
      { path: 'sales/orders',    loadComponent: () => import('./features/sales/orders/sales-orders-list.component').then(m => m.SalesOrdersListComponent) },
      { path: 'sales/deliveries',loadComponent: () => import('./features/sales/deliveries/deliveries-list.component').then(m => m.DeliveriesListComponent) },
      { path: 'sales/invoices',  loadComponent: () => import('./features/sales/invoices/invoices-list.component').then(m => m.InvoicesListComponent) },
      { path: 'sales/receipts',  loadComponent: () => import('./features/sales/receipts/receipts-list.component').then(m => m.ReceiptsListComponent) },
      { path: 'sales/returns',   loadComponent: () => import('./features/sales/returns/sales-returns-list.component').then(m => m.SalesReturnsListComponent) },

      // ── Accounts ─────────────────────────────────────────────────────────
      { path: 'accounts/coa',      loadComponent: () => import('./features/accounts/coa/coa.component').then(m => m.CoaComponent) },
      { path: 'accounts/periods',  loadComponent: () => import('./features/accounts/periods/periods.component').then(m => m.PeriodsComponent) },
      { path: 'accounts/vouchers', loadComponent: () => import('./features/accounts/vouchers/vouchers-list.component').then(m => m.VouchersListComponent) },

      // ── Reports ──────────────────────────────────────────────────────────
      { path: 'reports/trial-balance',  loadComponent: () => import('./features/reports/trial-balance/trial-balance.component').then(m => m.TrialBalanceComponent) },
      { path: 'reports/general-ledger', loadComponent: () => import('./features/reports/general-ledger/general-ledger.component').then(m => m.GeneralLedgerComponent) },
      { path: 'reports/pnl',            loadComponent: () => import('./features/reports/pnl/pnl.component').then(m => m.PnlComponent) },
      { path: 'reports/balance-sheet',  loadComponent: () => import('./features/reports/balance-sheet/balance-sheet.component').then(m => m.BalanceSheetComponent) },
      { path: 'reports/customer-ledger',loadComponent: () => import('./features/reports/customer-ledger/customer-ledger.component').then(m => m.CustomerLedgerComponent) },
      { path: 'reports/supplier-ledger',loadComponent: () => import('./features/reports/supplier-ledger/supplier-ledger.component').then(m => m.SupplierLedgerComponent) },
      { path: 'reports/cash-bank',      loadComponent: () => import('./features/reports/cash-bank/cash-bank.component').then(m => m.CashBankComponent) },

      // ── HR ───────────────────────────────────────────────────────────────
      { path: 'hr/employees',   loadComponent: () => import('./features/hr/employees/employees-list.component').then(m => m.EmployeesListComponent) },
      { path: 'hr/departments', loadComponent: () => import('./features/hr/departments/departments.component').then(m => m.DepartmentsComponent) },
      { path: 'hr/attendance',  loadComponent: () => import('./features/hr/attendance/attendance.component').then(m => m.AttendanceComponent) },
      { path: 'hr/leave',       loadComponent: () => import('./features/hr/leave/leave-list.component').then(m => m.LeaveListComponent) },
      { path: 'hr/payroll',     loadComponent: () => import('./features/hr/payroll/payroll-list.component').then(m => m.PayrollListComponent) },

      // ── Admin ────────────────────────────────────────────────────────────
      { path: 'admin/users',      loadComponent: () => import('./features/admin/users/users-list.component').then(m => m.UsersListComponent) },
      { path: 'admin/roles',      loadComponent: () => import('./features/admin/roles/roles.component').then(m => m.RolesComponent) },
      { path: 'admin/audit-logs', loadComponent: () => import('./features/admin/audit-logs/audit-logs.component').then(m => m.AuditLogsComponent) },
      { path: 'admin/settings',   loadComponent: () => import('./features/admin/settings/settings.component').then(m => m.SettingsComponent) },

      // Catch-all
      { path: '**', redirectTo: 'dashboard' }
    ]
  }
];
