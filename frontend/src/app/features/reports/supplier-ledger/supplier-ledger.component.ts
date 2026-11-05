import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-supplier-ledger',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Supplier Ledger" [breadcrumbs]="[{ label: 'Reports' }, { label: 'Supplier Ledger' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="grid grid-cols-4 gap-4 items-end">
        <div>
          <label class="form-label">Supplier *</label>
          <select [(ngModel)]="supplierId" class="form-input">
            <option [ngValue]="null">Select Supplier</option>
            @for (s of suppliers(); track s.id) {
              <option [value]="s.id">{{ s.name }} ({{ s.code }})</option>
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
        <button class="btn btn-primary" (click)="loadReport()">View Statement</button>
      </div>
    </div>

    <div class="card">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">Supplier Statement of Account</h2>
        @if (selectedSupplier) {
          <p class="text-sm font-semibold text-blue-700 mt-1">{{ selectedSupplier.name }} ({{ selectedSupplier.code }})</p>
        }
        <p class="text-xs text-gray-500 mt-0.5">Period: {{ fromDate }} to {{ toDate }}</p>
      </div>

      <!-- Summary metrics -->
      <div class="grid grid-cols-4 gap-4 mb-5 text-center">
        <div class="p-3 bg-gray-50 rounded-lg border">
          <span class="text-xs text-gray-500 block">Opening Balance</span>
          <span class="text-sm font-bold text-gray-900">\${{ openingBalance() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100">
          <span class="text-xs text-emerald-600 block">Total Payments (Dr)</span>
          <span class="text-sm font-bold text-emerald-800">\${{ totalDebit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
          <span class="text-xs text-blue-600 block">Total Purchases (Cr)</span>
          <span class="text-sm font-bold text-blue-800">\${{ totalCredit() | number:'1.2-2' }}</span>
        </div>
        <div class="p-3 bg-amber-50 rounded-lg border border-amber-100">
          <span class="text-xs text-amber-700 block">Closing Balance Payable</span>
          <span class="text-sm font-bold text-amber-900">\${{ closingBalance() | number:'1.2-2' }}</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-100 border-b">
            <tr>
              <th class="p-2.5 text-left">Date</th>
              <th class="p-2.5 text-left">Voucher / Ref #</th>
              <th class="p-2.5 text-left">Narration / Description</th>
              <th class="p-2.5 text-right">Debit (Paid)</th>
              <th class="p-2.5 text-right">Credit (Purchased)</th>
              <th class="p-2.5 text-right">Running Balance</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr><td colspan="6" class="p-6 text-center text-gray-400">Loading supplier ledger...</td></tr>
            } @else if (!entries().length) {
              <tr><td colspan="6" class="p-6 text-center text-gray-400">No transactions recorded for this supplier in this period</td></tr>
            }
            @for (e of entries(); track $index) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2.5">{{ e.date }}</td>
                <td class="p-2.5 font-mono font-medium text-blue-600">{{ e.voucher_number || e.reference || '-' }}</td>
                <td class="p-2.5">{{ e.description || e.narration || 'Purchase transaction' }}</td>
                <td class="p-2.5 text-right text-emerald-700">{{ e.debit > 0 ? (e.debit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right">{{ e.credit > 0 ? (e.credit | number:'1.2-2') : '-' }}</td>
                <td class="p-2.5 text-right font-semibold text-gray-900">{{ (e.running_balance ?? e.balance ?? 0) | number:'1.2-2' }}</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    </div>
  `
})
export class SupplierLedgerComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  suppliers = signal<any[]>([]);
  entries = signal<any[]>([]);
  openingBalance = signal(0);
  closingBalance = signal(0);
  totalDebit = signal(0);
  totalCredit = signal(0);
  loading = signal(false);

  supplierId: any = null;
  selectedSupplier: any = null;
  fromDate = new Date(new Date().getFullYear(), 0, 1).toISOString().substring(0, 10);
  toDate = new Date().toISOString().substring(0, 10);

  ngOnInit() {
    this.api.get<any>('/suppliers').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.suppliers.set(list);
        if (list.length) {
          this.supplierId = list[0].id;
          this.loadReport();
        }
      }
    });
  }

  loadReport() {
    if (!this.supplierId) return;
    this.loading.set(true);
    this.selectedSupplier = this.suppliers().find(s => s.id == this.supplierId);

    this.api.get<any>('/reports/supplier-ledger', { supplier_id: this.supplierId, start_date: this.fromDate, end_date: this.toDate, from_date: this.fromDate, to_date: this.toDate }).subscribe({
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
        this.toast.error('Failed to load supplier statement');
        this.loading.set(false);
      }
    });
  }

  print() {
    window.print();
  }
}
