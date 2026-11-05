import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-general-ledger',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="General Ledger" [breadcrumbs]="[{ label: 'Reports' }, { label: 'General Ledger' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="grid grid-cols-4 gap-4 items-end">
        <div>
          <label class="form-label">Select Account *</label>
          <select [(ngModel)]="accountId" class="form-input">
            <option [ngValue]="null">Select Account</option>
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
        <button class="btn btn-primary" (click)="loadReport()">Generate Ledger</button>
      </div>
    </div>

    <div class="card">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">General Ledger Statement</h2>
        @if (selectedAccount) {
          <p class="text-sm font-semibold text-blue-700 mt-1">[{{ selectedAccount.code }}] {{ selectedAccount.name }} ({{ selectedAccount.type | uppercase }})</p>
        }
        <p class="text-xs text-gray-500 mt-0.5">Period: {{ fromDate }} to {{ toDate }}</p>
      </div>

      <!-- Summary metrics -->
      <div class="grid grid-cols-4 gap-4 mb-5 text-center">
        <div class="p-3 bg-gray-50 rounded-lg border">
          <span class="text-xs text-gray-500 block">Opening Balance</span>
          <span class="text-sm font-bold text-gray-900">\${{ openingBalance() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
          <span class="text-xs text-blue-600 block">Total Debits</span>
          <span class="text-sm font-bold text-blue-800">\${{ totalDebit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-indigo-50 rounded-lg border border-indigo-100">
          <span class="text-xs text-indigo-600 block">Total Credits</span>
          <span class="text-sm font-bold text-indigo-800">\${{ totalCredit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
          <span class="text-xs text-emerald-600 block">Closing Balance</span>
          <span class="text-sm font-bold text-emerald-800">\${{ closingBalance() | number:'1.2-2' }}</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-100 border-b">
            <tr>
              <th class="p-2.5 text-left">Date</th>
              <th class="p-2.5 text-left">Voucher #</th>
              <th class="p-2.5 text-left">Type</th>
              <th class="p-2.5 text-left">Party / Subledger</th>
              <th class="p-2.5 text-left">Narration</th>
              <th class="p-2.5 text-right">Debit</th>
              <th class="p-2.5 text-right">Credit</th>
              <th class="p-2.5 text-right">Running Balance</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr><td colspan="8" class="p-6 text-center text-gray-400">Loading ledger entries...</td></tr>
            } @else if (!entries().length) {
              <tr><td colspan="8" class="p-6 text-center text-gray-400">No transactions recorded for this account in this period</td></tr>
            }
            @for (e of entries(); track $index) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5">{{ e.date }}</td>
                <td class="p-2.5 font-mono font-medium text-blue-600">{{ e.voucher_number || e.reference || '-' }}</td>
                <td class="p-2.5 font-semibold text-gray-700 capitalize">{{ e.voucher_type || '-' }}</td>
                <td class="p-2.5 text-gray-600">{{ e.party_name || '-' }}</td>
                <td class="p-2.5 text-gray-700">{{ e.description || e.narration || '-' }}</td>
                <td class="p-2.5 text-right font-medium">{{ e.debit > 0 ? (e.debit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right font-medium">{{ e.credit > 0 ? (e.credit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right font-bold text-gray-900">{{ (e.running_balance ?? e.balance ?? 0) | number:'1.2-2' }}</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    </div>
  `
})
export class GeneralLedgerComponent implements OnInit {
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
  selectedAccount: any = null;
  fromDate = new Date(new Date().getFullYear(), 0, 1).toISOString().substring(0, 10);
  toDate = new Date().toISOString().substring(0, 10);

  ngOnInit() {
    this.api.get<any>('/accounts').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.accounts.set(list);
        if (list.length) {
          // Default to Bank account if available (e.g. 1020 or 1010), else first account
          const bank = list.find((a: any) => a.code === '1020') || list.find((a: any) => a.code === '1010') || list[0];
          this.accountId = bank.id;
          this.loadReport();
        }
      }
    });
  }

  loadReport() {
    if (!this.accountId) return;
    this.loading.set(true);
    this.selectedAccount = this.accounts().find(a => a.id == this.accountId);

    this.api.get<any>('/reports/general-ledger', { account_id: this.accountId, start_date: this.fromDate, end_date: this.toDate, from_date: this.fromDate, to_date: this.toDate }).subscribe({
      next: (res) => {
        const data = res?.data ?? res;
        const list = Array.isArray(data) ? data : (data?.entries || []);
        this.entries.set(list);
        this.openingBalance.set(Number(data?.opening_balance) || 0);
        this.closingBalance.set(Number(data?.closing_balance) || 0);
        this.totalDebit.set(Number(data?.total_debit) || 0);
        this.totalCredit.set(Number(data?.total_credit) || 0);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load ledger');
        this.loading.set(false);
      }
    });
  }

  print() {
    window.print();
  }
}
