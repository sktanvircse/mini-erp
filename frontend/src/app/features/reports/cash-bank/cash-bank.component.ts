import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-cash-bank',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Cash & Bank Book" [breadcrumbs]="[{ label: 'Reports' }, { label: 'Cash & Bank Book' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="grid grid-cols-4 gap-4 items-end">
        <div>
          <label class="form-label">Cash/Bank Account *</label>
          <select [(ngModel)]="accountId" class="form-input" (change)="loadReport()">
            <option [ngValue]="null">All Cash & Bank Accounts</option>
            @for (a of accounts(); track a.id) {
              <option [value]="a.id">[{{ a.code }}] {{ a.name }}</option>
            }
          </select>
        </div>
        <div>
          <label class="form-label">From Date</label>
          <input type="date" [(ngModel)]="fromDate" class="form-input" />
        </div>
        <div>
          <label class="form-label">To Date</label>
          <input type="date" [(ngModel)]="toDate" class="form-input" />
        </div>
        <button class="btn btn-primary" (click)="loadReport()">View Book</button>
      </div>
    </div>

    <div class="card">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">Cash & Bank Transaction Book</h2>
        <p class="text-xs text-gray-500">Period: {{ fromDate }} to {{ toDate }}</p>
      </div>

      <!-- Summary metrics -->
      <div class="grid grid-cols-4 gap-4 mb-5 text-center">
        <div class="p-3 bg-gray-50 rounded-lg border">
          <span class="text-xs text-gray-500 block">Opening Cash/Bank</span>
          <span class="text-sm font-bold text-gray-900">\${{ openingBalance() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
          <span class="text-xs text-emerald-600 block">Total Receipts (Dr)</span>
          <span class="text-sm font-bold text-emerald-800">\${{ totalDebit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-rose-50 rounded-lg border border-rose-100">
          <span class="text-xs text-rose-600 block">Total Payments (Cr)</span>
          <span class="text-sm font-bold text-rose-800">\${{ totalCredit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
          <span class="text-xs text-blue-600 block">Closing Cash & Bank</span>
          <span class="text-sm font-bold text-blue-800">\${{ closingBalance() | number:'1.2-2' }}</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-100 border-b">
            <tr>
              <th class="p-2.5 text-left">Date</th>
              <th class="p-2.5 text-left">Voucher #</th>
              <th class="p-2.5 text-left">Account</th>
              <th class="p-2.5 text-left">Party</th>
              <th class="p-2.5 text-left">Narration</th>
              <th class="p-2.5 text-right">Receipts (Dr)</th>
              <th class="p-2.5 text-right">Payments (Cr)</th>
              <th class="p-2.5 text-right">Running Balance</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr><td colspan="8" class="p-6 text-center text-gray-400">Loading entries...</td></tr>
            } @else if (!entries().length) {
              <tr><td colspan="8" class="p-6 text-center text-gray-400">No cash or bank transactions recorded in this period</td></tr>
            }
            @for (e of entries(); track $index) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5">{{ e.date }}</td>
                <td class="p-2.5 font-mono font-medium text-blue-600">{{ e.voucher_number || e.reference || '-' }}</td>
                <td class="p-2.5 text-gray-700 font-medium">{{ e.account_name || '-' }}</td>
                <td class="p-2.5 text-gray-600">{{ e.party_name || '-' }}</td>
                <td class="p-2.5 text-gray-600">{{ e.description || e.narration || '-' }}</td>
                <td class="p-2.5 text-right text-emerald-700 font-semibold">{{ e.debit > 0 ? (e.debit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right text-rose-700 font-semibold">{{ e.credit > 0 ? (e.credit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right font-bold text-gray-900">{{ (e.running_balance ?? e.balance ?? 0) | number:'1.2-2' }}</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    </div>
  `
})
export class CashBankComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  accounts = signal<any[]>([]);
  entries = signal<any[]>([]);
  openingBalance = signal(0);
  closingBalance = signal(0);
  totalDebit = signal(0);
  totalCredit = signal(0);
  loading = signal(false);

  accountId: any = null;
  fromDate = new Date(new Date().getFullYear(), 0, 1).toISOString().substring(0, 10);
  toDate = new Date().toISOString().substring(0, 10);

  ngOnInit() {
    this.api.get<any>('/accounts', { type: 'asset' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        // Filter to Cash and Bank accounts (code 1010, 1020, etc.)
        const cashBankOnly = list.filter((a: any) =>
          a.code?.startsWith('101') || a.code?.startsWith('102') || a.name?.toLowerCase().includes('cash') || a.name?.toLowerCase().includes('bank')
        );
        this.accounts.set(cashBankOnly.length ? cashBankOnly : list);
        this.loadReport();
      }
    });
  }

  loadReport() {
    this.loading.set(true);
    const params: any = { start_date: this.fromDate, end_date: this.toDate, from_date: this.fromDate, to_date: this.toDate };
    if (this.accountId) params.account_id = this.accountId;

    this.api.get<any>('/reports/cash-bank-book', params).subscribe({
      next: (res) => {
        const data = res?.data ?? res;
        let list = Array.isArray(data) ? data : (data?.entries || []);
        if (this.accountId) {
          list = list.filter((e: any) => e.account_id == this.accountId);
        }
        this.entries.set(list);
        this.openingBalance.set(Number(data?.opening_balance) || 0);
        this.closingBalance.set(Number(data?.closing_balance) || 0);
        this.totalDebit.set(Number(data?.total_debit) || 0);
        this.totalCredit.set(Number(data?.total_credit) || 0);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load cash/bank book');
        this.loading.set(false);
      }
    });
  }

  print() {
    window.print();
  }
}
