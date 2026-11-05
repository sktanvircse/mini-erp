import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-pnl',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Profit & Loss Statement" [breadcrumbs]="[{ label: 'Reports' }, { label: 'Income Statement' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="flex items-end gap-4">
        <div>
          <label class="form-label">From Date</label>
          <input type="date" [(ngModel)]="fromDate" class="form-input" />
        </div>
        <div>
          <label class="form-label">To Date</label>
          <input type="date" [(ngModel)]="toDate" class="form-input" />
        </div>
        <button class="btn btn-primary" (click)="loadReport()">Generate P&L</button>
      </div>
    </div>

    <div class="card max-w-4xl mx-auto">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">Profit & Loss Statement</h2>
        <p class="text-xs text-gray-500">For the period: {{ fromDate }} to {{ toDate }}</p>
      </div>

      <!-- Income Section -->
      <div class="mb-6">
        <h3 class="font-bold text-sm text-gray-900 uppercase border-b pb-1 mb-2 flex justify-between">
          <span>Revenues & Incomes</span>
          <span class="text-emerald-700 font-semibold">\${{ totalIncome() | number:'1.2-2' }}</span>
        </h3>
        <table class="w-full text-xs">
          <tbody>
            @if (!incomes().length) {
              <tr><td colspan="2" class="p-3 text-center text-gray-400">No revenue accounts recorded</td></tr>
            }
            @for (item of incomes(); track (item.account_name || item.name)) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5 pl-4 font-medium text-gray-800">[{{ item.account_code || item.code }}] {{ item.account_name || item.name }}</td>
                <td class="p-2.5 text-right font-semibold text-gray-900">\${{ (item.amount || item.balance || 0) | number:'1.2-2' }}</td>
              </tr>
            }
          </tbody>
          <tfoot>
            <tr class="font-bold bg-green-50">
              <td class="p-2.5">Total Revenue / Income:</td>
              <td class="p-2.5 text-right text-emerald-800">\${{ totalIncome() | number:'1.2-2' }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Expenses Section -->
      <div class="mb-6">
        <h3 class="font-bold text-sm text-gray-900 uppercase border-b pb-1 mb-2 flex justify-between">
          <span>Operating Expenses & COGS</span>
          <span class="text-rose-700 font-semibold">\${{ totalExpenses() | number:'1.2-2' }}</span>
        </h3>
        <table class="w-full text-xs">
          <tbody>
            @if (!expenses().length) {
              <tr><td colspan="2" class="p-3 text-center text-gray-400">No expense accounts recorded</td></tr>
            }
            @for (item of expenses(); track (item.account_name || item.name)) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5 pl-4 font-medium text-gray-800">[{{ item.account_code || item.code }}] {{ item.account_name || item.name }}</td>
                <td class="p-2.5 text-right font-semibold text-gray-900">\${{ (item.amount || item.balance || 0) | number:'1.2-2' }}</td>
              </tr>
            }
          </tbody>
          <tfoot>
            <tr class="font-bold bg-red-50">
              <td class="p-2.5">Total Expenses:</td>
              <td class="p-2.5 text-right text-rose-800">\${{ totalExpenses() | number:'1.2-2' }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Net Profit / Loss -->
      <div class="p-4 rounded-xl border-2 font-bold text-base flex justify-between items-center"
           [class.bg-green-100]="netProfit() >= 0"
           [class.border-green-300]="netProfit() >= 0"
           [class.bg-red-100]="netProfit() < 0"
           [class.border-red-300]="netProfit() < 0">
        <div class="flex items-center gap-2">
          <span>NET {{ netProfit() >= 0 ? 'PROFIT' : 'LOSS' }}:</span>
          <span class="text-xs font-normal text-gray-600">({{ totalIncome() >= totalExpenses() ? 'Profitable operations' : 'Net expense exceeding revenue' }})</span>
        </div>
        <span class="text-xl" [class.text-green-800]="netProfit() >= 0" [class.text-red-800]="netProfit() < 0">
          \${{ netProfit() | number:'1.2-2' }}
        </span>
      </div>
    </div>
  `
})
export class PnlComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  fromDate = new Date(new Date().getFullYear(), 0, 1).toISOString().substring(0, 10);
  toDate = new Date().toISOString().substring(0, 10);

  incomes = signal<any[]>([]);
  expenses = signal<any[]>([]);
  totalIncome = signal(0);
  totalExpenses = signal(0);
  netProfit = signal(0);

  ngOnInit() {
    this.loadReport();
  }

  loadReport() {
    this.api.get<any>('/reports/profit-loss', { start_date: this.fromDate, end_date: this.toDate, from_date: this.fromDate, to_date: this.toDate }).subscribe({
      next: (res) => {
        const data = res?.data ?? res;
        const inc = data?.incomes ?? data?.revenue_accounts ?? [];
        const exp = data?.expenses ?? [
          ...(data?.cogs_items || []),
          ...(data?.operating_expenses || [])
        ];
        this.incomes.set(inc);
        this.expenses.set(exp);

        const incTot = data?.total_income != null ? Number(data.total_income) : (Number(data?.total_revenue) || inc.reduce((a: number, c: any) => a + Number(c.amount || 0), 0));
        const expTot = data?.total_expenses != null ? Number(data.total_expenses) : (Number(data?.total_operating || 0) + Number(data?.total_cogs || 0));
        const net = data?.net_profit != null ? Number(data.net_profit) : (incTot - expTot);

        this.totalIncome.set(incTot);
        this.totalExpenses.set(expTot);
        this.netProfit.set(net);
      },
      error: () => this.toast.error('Failed to load P&L')
    });
  }

  print() {
    window.print();
  }
}
