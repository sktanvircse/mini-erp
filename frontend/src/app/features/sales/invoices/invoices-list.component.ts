import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-invoices-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Sales Invoices" [breadcrumbs]="[{ label: 'Sales' }, { label: 'Invoices' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Invoice</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="invoices()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Sales Invoice</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Customer *</label>
                <select [(ngModel)]="formData.customer_id" name="customer_id" required class="form-input">
                  <option [ngValue]="null">Select Customer</option>
                  @for (c of customers(); track c.id) {
                    <option [value]="c.id">{{ c.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Invoice Date *</label>
                <input type="date" [(ngModel)]="formData.invoice_date" name="invoice_date" required class="form-input" />
              </div>
              <div>
                <label class="form-label">Due Date *</label>
                <input type="date" [(ngModel)]="formData.due_date" name="due_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Notes</label>
              <input type="text" [(ngModel)]="formData.notes" name="notes" class="form-input" placeholder="Invoice notes..." />
            </div>

            <!-- Items -->
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-gray-700 text-sm">Billable Items</h4>
                <button type="button" class="btn btn-secondary btn-sm" (click)="addItemLine()">+ Add Item</button>
              </div>

              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right w-20">Qty</th>
                      <th class="p-2 text-right w-24">Price</th>
                      <th class="p-2 text-right w-16">Tax %</th>
                      <th class="p-2 text-right w-16">Disc %</th>
                      <th class="p-2 text-right w-24">Total</th>
                      <th class="p-2 w-10"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @for (line of formData.items; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2">
                          <select [(ngModel)]="line.product_id" (change)="onProductSelect(line)" name="prod_{{$index}}" required class="form-input text-xs">
                            <option [ngValue]="null">Select Product</option>
                            @for (p of products(); track p.id) {
                              <option [value]="p.id">{{ p.name }}</option>
                            }
                          </select>
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.quantity" name="qty_{{$index}}" min="1" required class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.unit_price" name="price_{{$index}}" min="0" step="0.01" required class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.tax_rate" name="tax_{{$index}}" min="0" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.discount" name="disc_{{$index}}" min="0" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2 text-right font-medium">
                          {{ calculateLineTotal(line) | number:'1.2-2' }}
                        </td>
                        <td class="p-2 text-center">
                          <button type="button" class="text-red-500 font-bold" (click)="removeItemLine($index)">✕</button>
                        </td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            </div>

            <div class="flex justify-end gap-6 text-sm mb-6 border-t pt-4">
              <div>
                <span class="text-gray-500">Invoice Total: </span>
                <span class="text-lg font-bold text-gray-900">{{ calculateGrandTotal() | number:'1.2-2' }}</span>
              </div>
            </div>

            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Creating...' : 'Create Invoice' }}
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.invoice_number }}</h3>
              <p class="text-xs text-gray-500">Customer: {{ selectedItem.customer_name }} | Date: {{ selectedItem.invoice_date }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Qty</th>
                  <th class="p-2 text-right">Unit Price</th>
                  <th class="p-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right">{{ item.quantity }}</td>
                    <td class="p-2 text-right">{{ item.unit_price | number:'1.2-2' }}</td>
                    <td class="p-2 text-right font-medium">{{ item.total_price | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="grid grid-cols-3 gap-2 text-sm border-t pt-3 mb-4">
            <div>Total: <span class="font-bold">{{ selectedItem.total_amount | number:'1.2-2' }}</span></div>
            <div>Paid: <span class="font-bold text-green-600">{{ (selectedItem.paid_amount || 0) | number:'1.2-2' }}</span></div>
            <div>Due: <span class="font-bold text-red-600">{{ (selectedItem.balance_due || selectedItem.total_amount) | number:'1.2-2' }}</span></div>
          </div>

          <div class="flex justify-between items-center">
            <div class="flex gap-2">
              @if (selectedItem.status === 'draft') {
                <button class="btn btn-success btn-sm" (click)="postInvoice(selectedItem.id)">Post to GL</button>
              }
            </div>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class InvoicesListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  invoices = signal<any[]>([]);
  customers = signal<any[]>([]);
  products = signal<any[]>([]);
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
    invoice_date: new Date().toISOString().substring(0, 10),
    due_date: new Date(Date.now() + 30*86400000).toISOString().substring(0, 10),
    notes: '',
    items: []
  };

  columns = [
    { key: 'invoice_number', label: 'Invoice #', sortable: true },
    { key: 'customer_name', label: 'Customer', sortable: true },
    { key: 'invoice_date', label: 'Date', sortable: true },
    { key: 'total_amount', label: 'Total' },
    { key: 'balance_due', label: 'Balance Due' },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadCustomers();
    this.loadProducts();
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

  loadProducts() {
    this.api.get<any>('/products').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.products.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/sales/invoices', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.invoices.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load invoices');
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
      customer_id: this.customers()[0]?.id || null,
      invoice_date: new Date().toISOString().substring(0, 10),
      due_date: new Date(Date.now() + 30*86400000).toISOString().substring(0, 10),
      notes: '',
      items: [{ product_id: null, quantity: 1, unit_price: 0, tax_rate: 0, discount: 0 }]
    };
    this.modalOpen.set(true);
  }

  addItemLine() {
    this.formData.items.push({ product_id: null, quantity: 1, unit_price: 0, tax_rate: 0, discount: 0 });
  }

  removeItemLine(index: number) {
    if (this.formData.items.length > 1) {
      this.formData.items.splice(index, 1);
    }
  }

  onProductSelect(line: any) {
    const p = this.products().find(x => x.id === line.product_id);
    if (p) {
      line.unit_price = p.sale_price || 0;
    }
  }

  calculateLineTotal(line: any): number {
    const sub = (line.quantity || 0) * (line.unit_price || 0);
    const disc = sub * ((line.discount || 0) / 100);
    const afterDisc = sub - disc;
    const tax = afterDisc * ((line.tax_rate || 0) / 100);
    return afterDisc + tax;
  }

  calculateGrandTotal(): number {
    return (this.formData.items || []).reduce((acc: number, line: any) => acc + this.calculateLineTotal(line), 0);
  }

  save() {
    if (!this.formData.customer_id) {
      this.toast.error('Please select a customer');
      return;
    }
    if (!this.formData.items.length || this.formData.items.some((i: any) => !i.product_id || i.quantity <= 0)) {
      this.toast.error('Please specify valid product and quantity for all items');
      return;
    }
    this.saving.set(true);
    this.api.post('/sales/invoices', this.formData).subscribe({
      next: () => {
        this.toast.success('Invoice created');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to create invoice');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/sales/invoices/${item.id}`).subscribe({
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

  postInvoice(id: number) {
    this.api.patch(`/sales/invoices/${id}/post`).subscribe({
      next: () => {
        this.toast.success('Invoice posted to Accounts Receivable & COGS!');
        this.detailOpen.set(false);
        this.loadData();
      },
      error: (e) => this.toast.error(e?.error?.message || 'Posting failed')
    });
  }
}
