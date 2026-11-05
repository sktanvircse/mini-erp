import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-trial-balance',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Trial Balance" [breadcrumbs]="[{ label: 'Reports' }, { label: 'Trial Balance' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="flex items-end gap-4">
        <div>
          <label class="form-label">As of Date</label>
          <input type="date" [(ngModel)]="toDate" class="form-input" />
        </div>
        <button class="btn btn-primary" (click)="loadReport()">Generate Report</button>
      </div>
    </div>

    <div class="card">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">Trial Balance</h2>
        <p class="text-xs text-gray-500">As of: {{ toDate }}</p>
        @if (isBalanced()) {
          <span class="inline-block mt-2 px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold">
            ✓ Double-Entry Balanced (Debit == Credit)
          </span>
        } @else {
          <span class="inline-block mt-2 px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">
            ⚠ Out of Balance (Difference: \${{ difference() | number:'1.2-2' }})
          </span>
        }
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-100 border-b">
            <tr>
              <th class="p-2.5 text-left">Code</th>
              <th class="p-2.5 text-left">Account Name</th>
              <th class="p-2.5 text-left">Type</th>
              <th class="p-2.5 text-right">Debit Balance</th>
              <th class="p-2.5 text-right">Credit Balance</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr><td colspan="5" class="p-6 text-center text-gray-400">Loading trial balance...</td></tr>
            } @else if (!rows().length) {
              <tr><td colspan="5" class="p-6 text-center text-gray-400">No transactions found as of this date</td></tr>
            }
            @for (r of rows(); track (r.account_id || r.id || r.code)) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5 font-mono text-gray-600 font-semibold">{{ r.account_code || r.code }}</td>
                <td class="p-2.5 font-medium text-gray-900">{{ r.account_name || r.name }}</td>
                <td class="p-2.5 capitalize text-gray-500">{{ r.type }}</td>
                <td class="p-2.5 text-right font-medium">{{ (r.closing_debit ?? r.net_debit) > 0 ? ((r.closing_debit ?? r.net_debit) | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right font-medium">{{ (r.closing_credit ?? r.net_credit) > 0 ? ((r.closing_credit ?? r.net_credit) | number:'1.2-2') : '-' }}</td>
              </tr>
            }
          </tbody>
          <tfoot class="border-t-2 border-gray-900 bg-gray-50 font-bold">
            <tr>
              <td colspan="3" class="p-3 text-right text-gray-900 uppercase">TOTALS:</td>
              <td class="p-3 text-right text-sm text-blue-900">\${{ totalDebit() | number:'1.2-2' }}</td>
              <td class="p-3 text-right text-sm text-blue-900">\${{ totalCredit() | number:'1.2-2' }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  `
})
export class TrialBalanceComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  toDate = new Date().toISOString().substring(0, 10);

  rows = signal<any[]>([]);
  totalDebit = signal(0);
  totalCredit = signal(0);
  isBalanced = signal(true);
  difference = signal(0);
  loading = signal(false);

  ngOnInit() {
    this.loadReport();
  }

  loadReport() {
    this.loading.set(true);
    this.api.get<any>('/reports/trial-balance', { as_of_date: this.toDate, to_date: this.toDate }).subscribe({
      next: (res) => {
        const data = res?.data ?? res;
        const list = Array.isArray(data) ? data : (data?.items || []);
        this.rows.set(list);

        const dr = data?.total_debit != null ? Number(data.total_debit) : list.reduce((a: number, c: any) => a + (Number(c.closing_debit ?? c.net_debit) || 0), 0);
        const cr = data?.total_credit != null ? Number(data.total_credit) : list.reduce((a: number, c: any) => a + (Number(c.closing_credit ?? c.net_credit) || 0), 0);
        this.totalDebit.set(dr);
        this.totalCredit.set(cr);
        this.isBalanced.set(data?.is_balanced ?? (Math.abs(dr - cr) < 0.01));
        this.difference.set(data?.difference ?? Math.abs(dr - cr));
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load trial balance');
        this.loading.set(false);
      }
    });
  }

  print() {
    window.print();
  }
}
