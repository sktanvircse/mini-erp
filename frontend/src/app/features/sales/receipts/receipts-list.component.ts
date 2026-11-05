import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-receipts-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent],
  template: `
    <app-page-header title="Money Receipts" [breadcrumbs]="[{ label: 'Sales' }, { label: 'Receipts' }]">
      <button class="btn btn-primary" (click)="openModal()">+ Record Payment</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="receipts()"
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
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Record Customer Receipt</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Customer *</label>
                <select [(ngModel)]="formData.customer_id" (change)="onCustomerSelect()" name="customer_id" required class="form-input">
                  <option [ngValue]="null">Select Customer</option>
                  @for (c of customers(); track c.id) {
                    <option [value]="c.id">{{ c.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Receipt Date *</label>
                <input type="date" [(ngModel)]="formData.receipt_date" name="receipt_date" required class="form-input" />
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Amount Received *</label>
                <input type="number" [(ngModel)]="formData.amount" name="amount" min="0.01" step="0.01" required class="form-input" />
              </div>
              <div>
                <label class="form-label">Payment Mode *</label>
                <select [(ngModel)]="formData.payment_method" name="payment_method" required class="form-input">
                  <option value="cash">Cash</option>
                  <option value="bank">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div>
                <label class="form-label">Deposit Account *</label>
                <select [(ngModel)]="formData.bank_account_id" name="bank_account_id" required class="form-input">
                  <option [ngValue]="null">Select Account</option>
                  @for (a of accounts(); track a.id) {
                    <option [value]="a.id">{{ a.name }} ({{ a.code }})</option>
                  }
                </select>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Ref # / Cheque #</label>
              <input type="text" [(ngModel)]="formData.reference_number" name="ref_no" class="form-input" placeholder="Check/TRX ID..." />
            </div>

            <!-- Invoices to allocate -->
            @if (unpaidInvoices().length) {
              <div class="mb-4">
                <h4 class="font-medium text-gray-700 text-sm mb-2">Allocate to Invoices</h4>
                <div class="border rounded-lg overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b">
                      <tr>
                        <th class="p-2 text-left">Invoice #</th>
                        <th class="p-2 text-right">Balance Due</th>
                        <th class="p-2 text-right w-32">Allocate Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      @for (inv of unpaidInvoices(); track inv.id) {
                        <tr class="border-b last:border-0">
                          <td class="p-2 font-medium">{{ inv.invoice_number }}</td>
                          <td class="p-2 text-right text-gray-600">{{ inv.balance_due | number:'1.2-2' }}</td>
                          <td class="p-2">
                            <input type="number" [(ngModel)]="inv.allocated_amount" [name]="'alloc_' + inv.id" min="0" [max]="inv.balance_due" class="form-input text-xs text-right" />
                          </td>
                        </tr>
                      }
                    </tbody>
                  </table>
                </div>
              </div>
            }

            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Processing...' : 'Post Money Receipt' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Detail Modal -->
    @if (detailOpen() && selectedItem) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ selectedItem.receipt_number }}</h3>
          <p class="text-xs text-gray-500 mb-4">Customer: {{ selectedItem.customer_name }} | Date: {{ selectedItem.receipt_date }}</p>

          <div class="space-y-2 text-sm border-t border-b py-3 mb-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Amount:</span>
              <span class="font-bold text-green-600">{{ selectedItem.amount | number:'1.2-2' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Method:</span>
              <span class="uppercase font-medium">{{ selectedItem.payment_method }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Reference:</span>
              <span>{{ selectedItem.reference_number || '-' }}</span>
            </div>
          </div>

          <div class="flex justify-end">
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class ReceiptsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  receipts = signal<any[]>([]);
  customers = signal<any[]>([]);
  accounts = signal<any[]>([]);
  unpaidInvoices = signal<any[]>([]);
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
    customer_id: null,
    receipt_date: new Date().toISOString().substring(0, 10),
    amount: 0,
    payment_method: 'bank',
    bank_account_id: null,
    reference_number: '',
    notes: ''
  };

  columns = [
    { key: 'receipt_number', label: 'Receipt #', sortable: true },
    { key: 'customer_name', label: 'Customer', sortable: true },
    { key: 'receipt_date', label: 'Date', sortable: true },
    { key: 'payment_method', label: 'Method' },
    { key: 'amount', label: 'Amount' }
  ];

  ngOnInit() {
    this.loadCustomers();
    this.loadAccounts();
    this.loadData();
  }

  loadCustomers() {
    this.api.get<any>('/customers').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.customers.set(list);
      }
    });
  }

  loadAccounts() {
    this.api.get<any>('/accounts', { type: 'asset' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.accounts.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/sales/receipts', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.receipts.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load receipts');
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
      customer_id: null,
      receipt_date: new Date().toISOString().substring(0, 10),
      amount: 0,
      payment_method: 'bank',
      bank_account_id: this.accounts()[0]?.id || null,
      reference_number: '',
      notes: ''
    };
    this.unpaidInvoices.set([]);
    this.modalOpen.set(true);
  }

  onCustomerSelect() {
    if (!this.formData.customer_id) {
      this.unpaidInvoices.set([]);
      return;
    }
    this.api.get<any>('/sales/invoices', { customer_id: this.formData.customer_id, status: 'posted' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.unpaidInvoices.set(list.map((inv: any) => ({
          ...inv,
          allocated_amount: 0
        })));
      }
    });
  }

  save() {
    if (!this.formData.customer_id || this.formData.amount <= 0 || !this.formData.bank_account_id) {
      this.toast.error('Please enter valid customer, amount and deposit account');
      return;
    }
    this.saving.set(true);

    const allocations = this.unpaidInvoices()
      .filter((i: any) => i.allocated_amount > 0)
      .map((i: any) => ({
        invoice_id: i.id,
        allocated_amount: Number(i.allocated_amount)
      }));

    const payload = {
      ...this.formData,
      allocations
    };

    this.api.post('/sales/receipts', payload).subscribe({
      next: () => {
        this.toast.success('Payment recorded and GL updated!');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to record receipt');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.selectedItem = item;
    this.detailOpen.set(true);
  }
}
