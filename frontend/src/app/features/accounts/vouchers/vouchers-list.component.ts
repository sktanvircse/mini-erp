import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-vouchers-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Journal & Financial Vouchers" [breadcrumbs]="[{ label: 'Accounts' }, { label: 'Vouchers' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Voucher</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="vouchers()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
        (rowClick)="viewDetail($event)"
      />
    </div>

    <!-- Create Modal -->
    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Journal Voucher (Double-Entry)</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-4 gap-4 mb-4">
              <div>
                <label class="form-label">Type *</label>
                <select [(ngModel)]="formData.voucher_type" name="voucher_type" required class="form-input">
                  <option value="JV">Journal (JV)</option>
                  <option value="PV">Payment (PV)</option>
                  <option value="RV">Receipt (RV)</option>
                  <option value="CV">Contra (CV)</option>
                </select>
              </div>
              <div>
                <label class="form-label">Date *</label>
                <input type="date" [(ngModel)]="formData.voucher_date" name="voucher_date" required class="form-input" />
              </div>
              <div>
                <label class="form-label">Financial Period *</label>
                <select [(ngModel)]="formData.period_id" name="period_id" required class="form-input">
                  <option [ngValue]="null">Select Open Period</option>
                  @for (p of periods(); track p.id) {
                    <option [value]="p.id">{{ p.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Reference #</label>
                <input type="text" [(ngModel)]="formData.reference" name="reference" class="form-input" placeholder="Doc Ref..." />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Narration / Memo</label>
              <input type="text" [(ngModel)]="formData.narration" name="narration" class="form-input" placeholder="General explanation for the voucher..." />
            </div>

            <!-- Lines Table -->
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-gray-700 text-sm">Voucher Debit/Credit Lines</h4>
                <button type="button" class="btn btn-secondary btn-sm" (click)="addLine()">+ Add Line</button>
              </div>

              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Account</th>
                      <th class="p-2 text-left">Line Memo</th>
                      <th class="p-2 text-right w-28">Debit</th>
                      <th class="p-2 text-right w-28">Credit</th>
                      <th class="p-2 w-10"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @for (line of formData.lines; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2">
                          <select [(ngModel)]="line.account_id" name="acc_{{$index}}" required class="form-input text-xs">
                            <option [ngValue]="null">Select Account</option>
                            @for (a of accounts(); track a.id) {
                              <option [value]="a.id">[{{ a.code }}] {{ a.name }}</option>
                            }
                          </select>
                        </td>
                        <td class="p-2">
                          <input type="text" [(ngModel)]="line.narration" name="line_narr_{{$index}}" class="form-input text-xs" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.debit" (input)="onDebitChange(line)" name="dr_{{$index}}" min="0" step="0.01" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.credit" (input)="onCreditChange(line)" name="cr_{{$index}}" min="0" step="0.01" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2 text-center">
                          <button type="button" class="text-red-500 font-bold" (click)="removeLine($index)">✕</button>
                        </td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Double-entry balance check indicator -->
            <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border text-sm mb-6">
              <div>
                <span class="text-gray-600">Total Debit: </span>
                <span class="font-bold text-gray-900">{{ totalDebit() | number:'1.2-2' }}</span>
              </div>
              <div>
                <span class="text-gray-600">Total Credit: </span>
                <span class="font-bold text-gray-900">{{ totalCredit() | number:'1.2-2' }}</span>
              </div>
              <div>
                @if (isBalanced()) {
                  <span class="text-green-600 font-semibold">✓ Balanced (Difference: 0.00)</span>
                } @else {
                  <span class="text-red-600 font-semibold">✕ Unbalanced (Diff: {{ balanceDiff() | number:'1.2-2' }})</span>
                }
              </div>
            </div>

            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving() || !isBalanced()" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Create Voucher' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Detail Modal -->
    @if (detailOpen() && selectedItem) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.voucher_number }} ({{ selectedItem.voucher_type }})</h3>
              <p class="text-xs text-gray-500">Date: {{ selectedItem.voucher_date }} | Period: {{ selectedItem.period_name || '-' }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <p class="text-sm text-gray-600 mb-3">{{ selectedItem.narration }}</p>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Account</th>
                  <th class="p-2 text-left">Narration</th>
                  <th class="p-2 text-right">Debit</th>
                  <th class="p-2 text-right">Credit</th>
                </tr>
              </thead>
              <tbody>
                @for (line of selectedItem.lines; track line.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2 font-medium">[{{ line.account_code }}] {{ line.account_name }}</td>
                    <td class="p-2 text-gray-500">{{ line.narration || '-' }}</td>
                    <td class="p-2 text-right">{{ line.debit ? (line.debit | number:'1.2-2') : '-' }}</td>
                    <td class="p-2 text-right">{{ line.credit ? (line.credit | number:'1.2-2') : '-' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="flex justify-between items-center text-sm font-semibold border-t pt-3">
            <span>Total: {{ selectedItem.total_debit | number:'1.2-2' }}</span>
            <div class="flex gap-2">
              @if (selectedItem.status === 'draft') {
                <button class="btn btn-success btn-sm" (click)="postVoucher(selectedItem.id)">Post Voucher</button>
              }
              <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
            </div>
          </div>
        </div>
      </div>
    }
  `
})
export class VouchersListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  vouchers = signal<any[]>([]);
  accounts = signal<any[]>([]);
  periods = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  detailOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  selectedItem: any = null;

  formData: any = {
    voucher_type: 'JV',
    voucher_date: new Date().toISOString().substring(0, 10),
    period_id: null,
    reference: '',
    narration: '',
    lines: []
  };

  columns = [
    { key: 'voucher_number', label: 'Voucher #', sortable: true },
    { key: 'voucher_type', label: 'Type' },
    { key: 'voucher_date', label: 'Date', sortable: true },
    { key: 'narration', label: 'Narration' },
    { key: 'total_debit', label: 'Amount' },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadAccounts();
    this.loadPeriods();
    this.loadData();
  }

  loadAccounts() {
    this.api.get<any>('/accounts').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.accounts.set(list);
      }
    });
  }

  loadPeriods() {
    this.api.get<any>('/accounts/periods').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.periods.set(list.filter((p: any) => p.status === 'open'));
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/accounts/vouchers', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.vouchers.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load vouchers');
        this.loading.set(false);
      }
    });
  }

  onPageChange(p: number) {
    this.page.set(p);
    this.loadData();
  }

  onSearchChange(q: string) {
    this.searchQuery = q;
    this.page.set(1);
    this.loadData();
  }

  openModal() {
    this.formData = {
      voucher_type: 'JV',
      voucher_date: new Date().toISOString().substring(0, 10),
      period_id: this.periods()[0]?.id || null,
      reference: '',
      narration: '',
      lines: [
        { account_id: null, narration: '', debit: 0, credit: 0 },
        { account_id: null, narration: '', debit: 0, credit: 0 }
      ]
    };
    this.modalOpen.set(true);
  }

  addLine() {
    this.formData.lines.push({ account_id: null, narration: '', debit: 0, credit: 0 });
  }

  removeLine(index: number) {
    if (this.formData.lines.length > 2) {
      this.formData.lines.splice(index, 1);
    }
  }

  onDebitChange(line: any) {
    if (line.debit > 0) line.credit = 0;
  }

  onCreditChange(line: any) {
    if (line.credit > 0) line.debit = 0;
  }

  totalDebit(): number {
    return (this.formData.lines || []).reduce((acc: number, l: any) => acc + (Number(l.debit) || 0), 0);
  }

  totalCredit(): number {
    return (this.formData.lines || []).reduce((acc: number, l: any) => acc + (Number(l.credit) || 0), 0);
  }

  balanceDiff(): number {
    return Math.abs(this.totalDebit() - this.totalCredit());
  }

  isBalanced(): boolean {
    const dr = this.totalDebit();
    const cr = this.totalCredit();
    return dr > 0 && Math.abs(dr - cr) < 0.001;
  }

  save() {
    if (!this.formData.period_id) {
      this.toast.error('Financial period is required');
      return;
    }
    if (!this.isBalanced()) {
      this.toast.error('Total debit must equal total credit');
      return;
    }

    this.saving.set(true);
    const payload = {
      ...this.formData,
      total_debit: this.totalDebit(),
      total_credit: this.totalCredit()
    };

    this.api.post('/accounts/vouchers', payload).subscribe({
      next: () => {
        this.toast.success('Voucher created successfully!');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to create voucher');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/accounts/vouchers/${item.id}`).subscribe({
      next: (res) => {
        this.selectedItem = res?.data || res || item;
        this.detailOpen.set(true);
      },
      error: () => {
        this.selectedItem = item;
        this.detailOpen.set(true);
      }
    });
  }

  postVoucher(id: number) {
    this.api.patch(`/accounts/vouchers/${id}/post`).subscribe({
      next: () => {
        this.toast.success('Voucher permanently posted to General Ledger');
        this.detailOpen.set(false);
        this.loadData();
      },
      error: (e) => this.toast.error(e?.error?.message || 'Posting failed')
    });
  }
}
