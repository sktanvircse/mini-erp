import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';

interface DashboardData {
  today_sales: number;
  total_receivables: number;
  total_payables: number;
  cash_balance: number;
  low_stock_count: number;
  pending_purchase_orders: number;
  pending_sales_orders: number;
  monthly_sales: { month: string; amount: number }[];
  top_products: { product_name: string; total_qty: number; total_amount: number }[];
  recent_invoices: any[];
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div>
      <div class="mb-6">
        <h1 class="page-title">Dashboard</h1>
        <p class="text-gray-500 text-sm">Welcome back, {{ userName }}</p>
      </div>

      @if (loading()) {
        <div class="text-center py-20 text-gray-400">Loading…</div>
      } @else if (data()) {
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
          <div class="card">
            <p class="text-sm text-gray-500 mb-1">Today's Sales</p>
            <p class="text-2xl font-bold text-blue-600">{{ fmt(data()!.today_sales) }}</p>
          </div>
          <div class="card">
            <p class="text-sm text-gray-500 mb-1">Total Receivables</p>
            <p class="text-2xl font-bold text-green-600">{{ fmt(data()!.total_receivables) }}</p>
          </div>
          <div class="card">
            <p class="text-sm text-gray-500 mb-1">Total Payables</p>
            <p class="text-2xl font-bold text-red-600">{{ fmt(data()!.total_payables) }}</p>
          </div>
          <div class="card">
            <p class="text-sm text-gray-500 mb-1">Cash Balance</p>
            <p class="text-2xl font-bold text-purple-600">{{ fmt(data()!.cash_balance) }}</p>
          </div>
        </div>

        <!-- Alerts row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
          <a routerLink="/inventory/balances"
            class="card flex items-center gap-4 hover:border-orange-400 transition-colors">
            <span class="text-3xl">⚠️</span>
            <div>
              <p class="text-2xl font-bold text-orange-500">{{ data()!.low_stock_count }}</p>
              <p class="text-sm text-gray-500">Low Stock Items</p>
            </div>
          </a>
          <a routerLink="/purchase/orders"
            class="card flex items-center gap-4 hover:border-blue-400 transition-colors">
            <span class="text-3xl">📦</span>
            <div>
              <p class="text-2xl font-bold text-blue-600">{{ data()!.pending_purchase_orders }}</p>
              <p class="text-sm text-gray-500">Pending POs</p>
            </div>
          </a>
          <a routerLink="/sales/orders"
            class="card flex items-center gap-4 hover:border-green-400 transition-colors">
            <span class="text-3xl">🛒</span>
            <div>
              <p class="text-2xl font-bold text-green-600">{{ data()!.pending_sales_orders }}</p>
              <p class="text-sm text-gray-500">Pending Sales Orders</p>
            </div>
          </a>
        </div>

        <!-- Bottom row: monthly chart + top products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Monthly bar chart (simple CSS bars) -->
          <div class="card">
            <h3 class="font-semibold text-gray-700 mb-4">Monthly Sales</h3>
            <div class="flex items-end gap-2 h-40">
              @for (m of data()!.monthly_sales; track m.month) {
                <div class="flex flex-col items-center flex-1 gap-1">
                  <div [style.height.%]="barHeight(m.amount)"
                    class="w-full bg-blue-500 rounded-t-sm transition-all duration-500 min-h-[2px]"></div>
                  <span class="text-xs text-gray-400 rotate-45 origin-left">{{ m.month }}</span>
                </div>
              }
            </div>
          </div>

          <!-- Top products -->
          <div class="card">
            <h3 class="font-semibold text-gray-700 mb-4">Top Selling Products</h3>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-100">
                  <th class="text-left pb-2 text-gray-500 font-medium">Product</th>
                  <th class="text-right pb-2 text-gray-500 font-medium">Qty</th>
                  <th class="text-right pb-2 text-gray-500 font-medium">Amount</th>
                </tr>
              </thead>
              <tbody>
                @for (p of data()!.top_products; track p.product_name) {
                  <tr class="border-b border-gray-50 last:border-0">
                    <td class="py-2">{{ p.product_name }}</td>
                    <td class="py-2 text-right">{{ p.total_qty }}</td>
                    <td class="py-2 text-right font-medium">{{ fmt(p.total_amount) }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        </div>
      }
    </div>
  `
})
export class DashboardComponent implements OnInit {
  api  = inject(ApiService);
  auth = inject(AuthService);
  data    = signal<DashboardData | null>(null);
  loading = signal(true);

  get userName(): string {
    const u: any = this.auth.user();
    return u?.name || 'User';
  }

  ngOnInit() {
    this.api.get<DashboardData>('/dashboard').subscribe({
      next: (d: any) => { this.data.set(d); this.loading.set(false); },
      error: () => this.loading.set(false)
    });
  }

  fmt(n: number) {
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0);
  }

  barHeight(amount: number): number {
    const max = Math.max(...(this.data()?.monthly_sales.map(m => m.amount) ?? [1]));
    return max > 0 ? (amount / max) * 100 : 0;
  }
}
