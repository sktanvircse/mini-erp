import { Component, Input, Output, EventEmitter, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

interface NavItem {
  label: string;
  icon: string;
  link?: string;
  permission?: string | string[];
  children?: NavItem[];
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  styles: [`
    :host {
      display: flex;
      flex-direction: column;
      height: 100%;
      max-height: 100%;
      overflow: hidden;
    }
  `],
  template: `
    <aside [class]="collapsed ? 'w-16' : 'w-64'" class="flex flex-col h-full bg-gray-900 text-gray-100 transition-all duration-200 shrink-0 select-none">
      <!-- Logo -->
      <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-700 shrink-0">
        <span class="text-2xl">📦</span>
        @if (!collapsed) {
          <span class="font-bold text-lg tracking-tight">Mini ERP</span>
        }
      </div>

      <!-- Navigation -->
      <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden min-h-0">
        @for (item of visibleItems; track item.label) {
          <!-- Group with children -->
          @if (item.children) {
            <div class="mb-1">
              <button (click)="toggleGroup(item.label)"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors">
                <span class="text-lg w-5 text-center shrink-0">{{ item.icon }}</span>
                @if (!collapsed) {
                  <span class="flex-1 text-left truncate">{{ item.label }}</span>
                  <span class="text-xs">{{ openGroups.has(item.label) ? '▾' : '▸' }}</span>
                }
              </button>
              @if (!collapsed && openGroups.has(item.label)) {
                <div class="space-y-0.5">
                  @for (child of visibleChildren(item); track child.label) {
                    <a [routerLink]="child.link" routerLinkActive="bg-blue-700 text-white"
                      class="flex items-center gap-3 pl-10 pr-4 py-2 text-sm text-gray-400 hover:bg-gray-800 hover:text-white transition-colors">
                      <span class="shrink-0">{{ child.icon }}</span>
                      <span class="truncate">{{ child.label }}</span>
                    </a>
                  }
                </div>
              }
            </div>
          } @else {
            <a [routerLink]="item.link" routerLinkActive="bg-blue-700 text-white"
              [routerLinkActiveOptions]="{exact: item.link === '/'}"
              class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors mb-0.5">
              <span class="text-lg w-5 text-center shrink-0">{{ item.icon }}</span>
              @if (!collapsed) { <span class="truncate">{{ item.label }}</span> }
            </a>
          }
        }
      </nav>

      <!-- Collapse button -->
      <button (click)="toggle.emit()" class="flex items-center justify-center py-3 border-t border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 shrink-0">
        {{ collapsed ? '→' : '←' }}
      </button>
    </aside>
  `
})
export class SidebarComponent {
  @Input() collapsed = false;
  @Output() toggle = new EventEmitter<void>();

  auth = inject(AuthService);
  openGroups = new Set<string>(['Purchase', 'Sales', 'Inventory']);

  nav: NavItem[] = [
    { label: 'Dashboard', icon: '🏠', link: '/dashboard' },
    {
      label: 'Master Data', icon: '🗂️',
      children: [
        { label: 'Products',    icon: '📦', link: '/master/products',    permission: 'products.view' },
        { label: 'Categories',  icon: '🏷️', link: '/master/categories', permission: 'products.view' },
        { label: 'Units',       icon: '📐', link: '/master/units',       permission: 'products.view' },
        { label: 'Warehouses',  icon: '🏭', link: '/master/warehouses',  permission: 'warehouses.view' },
        { label: 'Customers',   icon: '🧑', link: '/master/customers',   permission: 'customers.view' },
        { label: 'Suppliers',   icon: '🏢', link: '/master/suppliers',   permission: 'suppliers.view' },
      ]
    },
    {
      label: 'Purchase', icon: '🛒',
      children: [
        { label: 'Requisitions', icon: '📋', link: '/purchase/requisitions', permission: 'purchase_requisitions.view' },
        { label: 'Orders',       icon: '📄', link: '/purchase/orders',       permission: 'purchase_orders.view' },
        { label: 'GRN',          icon: '📥', link: '/purchase/grn',          permission: 'goods_receipts.view' },
        { label: 'Returns',      icon: '↩️', link: '/purchase/returns',      permission: 'purchase_returns.view' },
      ]
    },
    {
      label: 'Inventory', icon: '🏗️',
      children: [
        { label: 'Stock Balances', icon: '📊', link: '/inventory/balances',  permission: 'stock.view' },
        { label: 'Stock Ledger',   icon: '📒', link: '/inventory/ledger',    permission: 'stock.view' },
        { label: 'Transfers',      icon: '↔️', link: '/inventory/transfers', permission: 'stock_transfers.view' },
      ]
    },
    {
      label: 'Sales', icon: '💰',
      children: [
        { label: 'Orders',       icon: '📄', link: '/sales/orders',    permission: 'sales_orders.view' },
        { label: 'Deliveries',   icon: '🚚', link: '/sales/deliveries',permission: 'deliveries.view' },
        { label: 'Invoices',     icon: '🧾', link: '/sales/invoices',  permission: 'invoices.view' },
        { label: 'Receipts',     icon: '💳', link: '/sales/receipts',  permission: 'money_receipts.view' },
        { label: 'Returns',      icon: '↩️', link: '/sales/returns',   permission: 'sales_returns.view' },
      ]
    },
    {
      label: 'Accounts', icon: '📊',
      children: [
        { label: 'Chart of Accounts', icon: '🌲', link: '/accounts/coa',      permission: 'accounts.view' },
        { label: 'Periods',           icon: '📅', link: '/accounts/periods',   permission: 'periods.view' },
        { label: 'Vouchers',          icon: '📝', link: '/accounts/vouchers',  permission: 'vouchers.view' },
      ]
    },
    {
      label: 'Reports', icon: '📈',
      children: [
        { label: 'Trial Balance',   icon: '⚖️', link: '/reports/trial-balance',  permission: 'reports.view' },
        { label: 'General Ledger',  icon: '📒', link: '/reports/general-ledger', permission: 'reports.view' },
        { label: 'P&L',             icon: '📉', link: '/reports/pnl',            permission: 'reports.view' },
        { label: 'Balance Sheet',   icon: '📋', link: '/reports/balance-sheet',  permission: 'reports.view' },
        { label: 'Customer Ledger', icon: '👤', link: '/reports/customer-ledger',permission: 'reports.view' },
        { label: 'Supplier Ledger', icon: '🏢', link: '/reports/supplier-ledger',permission: 'reports.view' },
        { label: 'Cash/Bank Book',  icon: '🏦', link: '/reports/cash-bank',      permission: 'reports.view' },
      ]
    },
    {
      label: 'HR', icon: '👥',
      children: [
        { label: 'Employees',    icon: '👤', link: '/hr/employees',   permission: 'employees.view' },
        { label: 'Departments',  icon: '🏢', link: '/hr/departments', permission: 'employees.view' },
        { label: 'Attendance',   icon: '✅', link: '/hr/attendance',  permission: 'attendance.view' },
        { label: 'Leave',        icon: '🏖️', link: '/hr/leave',       permission: 'leave_requests.view' },
        { label: 'Payroll',      icon: '💵', link: '/hr/payroll',     permission: 'payroll.view' },
      ]
    },
    {
      label: 'Admin', icon: '⚙️',
      children: [
        { label: 'Users',      icon: '👤', link: '/admin/users',      permission: 'users.view' },
        { label: 'Roles',      icon: '🛡️', link: '/admin/roles',      permission: 'roles.view' },
        { label: 'Audit Logs', icon: '📜', link: '/admin/audit-logs', permission: 'audit_logs.view' },
        { label: 'Settings',   icon: '🔧', link: '/admin/settings',   permission: 'settings.view' },
      ]
    },
  ];

  get visibleItems(): NavItem[] {
    return this.nav.filter(item => {
      if (!item.permission) return true;
      const perms = Array.isArray(item.permission) ? item.permission : [item.permission];
      return this.auth.hasAnyPermission(perms);
    });
  }

  visibleChildren(item: NavItem): NavItem[] {
    return (item.children ?? []).filter(c => {
      if (!c.permission) return true;
      const perms = Array.isArray(c.permission) ? c.permission : [c.permission];
      return this.auth.hasAnyPermission(perms);
    });
  }

  toggleGroup(label: string) {
    if (this.openGroups.has(label)) this.openGroups.delete(label);
    else this.openGroups.add(label);
  }
}
