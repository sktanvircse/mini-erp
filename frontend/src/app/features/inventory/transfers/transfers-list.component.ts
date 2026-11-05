import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-transfers-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Stock Transfers" [breadcrumbs]="[{ label: 'Inventory' }, { label: 'Transfers' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Stock Transfer</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="transfers()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Transfer Stock Between Warehouses</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Source Warehouse *</label>
                <select [(ngModel)]="formData.from_warehouse_id" name="from_warehouse_id" required class="form-input">
                  <option [ngValue]="null">Select Source</option>
                  @for (w of warehouses(); track w.id) {
                    <option [value]="w.id">{{ w.name }} ({{ w.code }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Target Warehouse *</label>
                <select [(ngModel)]="formData.to_warehouse_id" name="to_warehouse_id" required class="form-input">
                  <option [ngValue]="null">Select Target</option>
                  @for (w of warehouses(); track w.id) {
                    <option [value]="w.id">{{ w.name }} ({{ w.code }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Transfer Date *</label>
                <input type="date" [(ngModel)]="formData.transfer_date" name="transfer_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Notes</label>
              <input type="text" [(ngModel)]="formData.notes" name="notes" class="form-input" placeholder="Transit details..." />
            </div>

            <!-- Items -->
            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-gray-700 text-sm">Items to Transfer</h4>
                <button type="button" class="btn btn-secondary btn-sm" (click)="addItemLine()">+ Add Product</button>
              </div>

              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right w-32">Transfer Qty</th>
                      <th class="p-2 w-10"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @for (line of formData.items; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2">
                          <select [(ngModel)]="line.product_id" name="prod_{{$index}}" required class="form-input text-xs">
                            <option [ngValue]="null">Select Product</option>
                            @for (p of products(); track p.id) {
                              <option [value]="p.id">{{ p.name }} ({{ p.code }})</option>
                            }
                          </select>
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.quantity" name="qty_{{$index}}" min="1" required class="form-input text-xs text-right" />
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

            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Transferring...' : 'Execute Transfer' }}
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.transfer_number }}</h3>
              <p class="text-xs text-gray-500">From: {{ selectedItem.from_warehouse_name }} ➔ To: {{ selectedItem.to_warehouse_name }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Quantity</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right font-medium">{{ item.quantity }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="flex justify-end">
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class TransfersListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  transfers = signal<any[]>([]);
  warehouses = signal<any[]>([]);
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
    from_warehouse_id: null,
    to_warehouse_id: null,
    transfer_date: new Date().toISOString().substring(0, 10),
    notes: '',
    items: []
  };

  columns = [
    { key: 'transfer_number', label: 'Transfer #', sortable: true },
    { key: 'from_warehouse_name', label: 'From' },
    { key: 'to_warehouse_name', label: 'To' },
    { key: 'transfer_date', label: 'Date', sortable: true },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadWarehouses();
    this.loadProducts();
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
    this.api.get<any>('/inventory/transfers', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.transfers.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load transfers');
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
      from_warehouse_id: null,
      to_warehouse_id: null,
      transfer_date: new Date().toISOString().substring(0, 10),
      notes: '',
      items: [{ product_id: null, quantity: 1 }]
    };
    this.modalOpen.set(true);
  }

  addItemLine() {
    this.formData.items.push({ product_id: null, quantity: 1 });
  }

  removeItemLine(index: number) {
    if (this.formData.items.length > 1) {
      this.formData.items.splice(index, 1);
    }
  }

  save() {
    if (!this.formData.from_warehouse_id || !this.formData.to_warehouse_id) {
      this.toast.error('Both source and destination warehouses are required');
      return;
    }
    if (this.formData.from_warehouse_id === this.formData.to_warehouse_id) {
      this.toast.error('Source and destination warehouses cannot be the same');
      return;
    }
    const validItems = this.formData.items.filter((i: any) => i.product_id && i.quantity > 0);
    if (!validItems.length) {
      this.toast.error('Please specify valid product and quantity');
      return;
    }

    this.saving.set(true);
    this.api.post('/inventory/transfers', { ...this.formData, items: validItems }).subscribe({
      next: () => {
        this.toast.success('Stock transferred successfully!');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Transfer failed');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/inventory/transfers/${item.id}`).subscribe({
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
