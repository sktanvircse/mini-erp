import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-sales-returns-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Sales Returns" [breadcrumbs]="[{ label: 'Sales' }, { label: 'Returns' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Sales Return</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="returns()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Sales Return</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Invoice Ref *</label>
                <select [(ngModel)]="formData.invoice_id" (change)="onInvoiceSelect()" name="invoice_id" required class="form-input">
                  <option [ngValue]="null">Select Invoice</option>
                  @for (inv of invoiceList(); track inv.id) {
                    <option [value]="inv.id">{{ inv.invoice_number }} ({{ inv.customer_name }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Warehouse *</label>
                <select [(ngModel)]="formData.warehouse_id" name="warehouse_id" required class="form-input">
                  <option [ngValue]="null">Select Warehouse</option>
                  @for (w of warehouses(); track w.id) {
                    <option [value]="w.id">{{ w.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Return Date *</label>
                <input type="date" [(ngModel)]="formData.return_date" name="return_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Reason *</label>
              <input type="text" [(ngModel)]="formData.reason" name="reason" required class="form-input" placeholder="Customer dissatisfied, defect..." />
            </div>

            <div class="mb-4">
              <h4 class="font-medium text-gray-700 text-sm mb-2">Items to Return</h4>
              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right">Invoiced Qty</th>
                      <th class="p-2 text-right w-24">Return Qty</th>
                      <th class="p-2 text-right">Unit Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if (!formData.items.length) {
                      <tr><td colspan="4" class="p-4 text-center text-gray-400">Select an Invoice first</td></tr>
                    }
                    @for (line of formData.items; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2 font-medium">{{ line.product_name }}</td>
                        <td class="p-2 text-right">{{ line.max_qty }}</td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.quantity" name="qty_{{$index}}" min="0" [max]="line.max_qty" required class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2 text-right">{{ line.unit_price | number:'1.2-2' }}</td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving() || !formData.items.length" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Submit Sales Return' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Detail Modal -->
    @if (detailOpen() && selectedItem) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-xl w-full mx-4">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.return_number }}</h3>
              <p class="text-xs text-gray-500">Invoice: {{ selectedItem.invoice_number }} | Reason: {{ selectedItem.reason }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Qty</th>
                  <th class="p-2 text-right">Price</th>
                  <th class="p-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right font-medium">{{ item.quantity }}</td>
                    <td class="p-2 text-right">{{ item.unit_price | number:'1.2-2' }}</td>
                    <td class="p-2 text-right">{{ item.total_price | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="flex justify-between items-center text-sm font-semibold border-t pt-3">
            <span>Total Credit Note: {{ selectedItem.total_amount | number:'1.2-2' }}</span>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class SalesReturnsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  returns = signal<any[]>([]);
  invoiceList = signal<any[]>([]);
  warehouses = signal<any[]>([]);
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
    invoice_id: null,
    warehouse_id: null,
    return_date: new Date().toISOString().substring(0, 10),
    reason: '',
    items: []
  };

  columns = [
    { key: 'return_number', label: 'Return #', sortable: true },
    { key: 'invoice_number', label: 'Invoice #' },
    { key: 'customer_name', label: 'Customer' },
    { key: 'return_date', label: 'Date', sortable: true },
    { key: 'total_amount', label: 'Total Value' }
  ];

  ngOnInit() {
    this.loadWarehouses();
    this.loadInvoices();
    this.loadData();
  }

  loadWarehouses() {
    this.api.get<any>('/warehouses').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.warehouses.set(list);
      }
    });
  }

  loadInvoices() {
    this.api.get<any>('/sales/invoices', { status: 'posted' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.invoiceList.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/sales/returns', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.returns.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load sales returns');
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
    this.loadInvoices();
    this.formData = {
      invoice_id: null,
      warehouse_id: this.warehouses()[0]?.id || null,
      return_date: new Date().toISOString().substring(0, 10),
      reason: '',
      items: []
    };
    this.modalOpen.set(true);
  }

  onInvoiceSelect() {
    if (!this.formData.invoice_id) {
      this.formData.items = [];
      return;
    }
    this.api.get<any>(`/sales/invoices/${this.formData.invoice_id}`).subscribe({
      next: (res) => {
        const inv = res?.data || res;
        this.formData.items = (inv.items || []).map((it: any) => ({
          product_id: it.product_id,
          product_name: it.product_name,
          max_qty: it.quantity,
          quantity: 0,
          unit_price: it.unit_price
        }));
      }
    });
  }

  save() {
    if (!this.formData.invoice_id || !this.formData.warehouse_id || !this.formData.reason) {
      this.toast.error('Invoice, Warehouse and Reason are required');
      return;
    }
    const returnItems = this.formData.items.filter((i: any) => i.quantity > 0);
    if (!returnItems.length) {
      this.toast.error('At least one item must have return quantity > 0');
      return;
    }

    this.saving.set(true);
    const payload = {
      ...this.formData,
      items: returnItems
    };

    this.api.post('/sales/returns', payload).subscribe({
      next: () => {
        this.toast.success('Sales return created! Inventory returned & AR credited.');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to process return');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/sales/returns/${item.id}`).subscribe({
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
}
