import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-deliveries-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Deliveries" [breadcrumbs]="[{ label: 'Sales' }, { label: 'Deliveries' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Delivery</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="deliveries()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Delivery Note</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Sales Order *</label>
                <select [(ngModel)]="formData.so_id" (change)="onSoSelect()" name="so_id" required class="form-input">
                  <option [ngValue]="null">Select Confirmed SO</option>
                  @for (so of confirmedSos(); track so.id) {
                    <option [value]="so.id">{{ so.so_number }} - {{ so.customer_name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Warehouse *</label>
                <select [(ngModel)]="formData.warehouse_id" name="warehouse_id" required class="form-input">
                  <option [ngValue]="null">Select Warehouse</option>
                  @for (w of warehouses(); track w.id) {
                    <option [value]="w.id">{{ w.name }} ({{ w.code }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Delivery Date *</label>
                <input type="date" [(ngModel)]="formData.delivery_date" name="delivery_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Notes</label>
              <input type="text" [(ngModel)]="formData.notes" name="notes" class="form-input" placeholder="Tracking number / driver..." />
            </div>

            <div class="mb-4">
              <h4 class="font-medium text-gray-700 text-sm mb-2">Items to Dispatch</h4>
              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right">Ordered</th>
                      <th class="p-2 text-right">Already Delivered</th>
                      <th class="p-2 text-right w-24">Deliver Now</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if (!formData.items.length) {
                      <tr><td colspan="4" class="p-4 text-center text-gray-400">Select a Sales Order to load items</td></tr>
                    }
                    @for (line of formData.items; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2 font-medium">{{ line.product_name }}</td>
                        <td class="p-2 text-right">{{ line.quantity_ordered }}</td>
                        <td class="p-2 text-right">{{ line.previously_delivered || 0 }}</td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.quantity_delivered" name="del_{{$index}}" min="0" [max]="line.quantity_ordered - (line.previously_delivered || 0)" required class="form-input text-xs text-right" />
                        </td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving() || !formData.items.length" class="btn btn-primary">
                {{ saving() ? 'Dispatching...' : 'Dispatch Delivery' }}
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.delivery_number }}</h3>
              <p class="text-xs text-gray-500">SO: {{ selectedItem.so_number }} | Customer: {{ selectedItem.customer_name }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Delivered Qty</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right font-medium">{{ item.quantity_delivered }}</td>
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
export class DeliveriesListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  deliveries = signal<any[]>([]);
  confirmedSos = signal<any[]>([]);
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
    so_id: null,
    warehouse_id: null,
    delivery_date: new Date().toISOString().substring(0, 10),
    notes: '',
    items: []
  };

  columns = [
    { key: 'delivery_number', label: 'Delivery #', sortable: true },
    { key: 'so_number', label: 'SO #' },
    { key: 'customer_name', label: 'Customer' },
    { key: 'warehouse_name', label: 'Warehouse' },
    { key: 'delivery_date', label: 'Date', sortable: true },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadWarehouses();
    this.loadConfirmedSos();
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

  loadConfirmedSos() {
    this.api.get<any>('/sales/orders', { status: 'confirmed' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.confirmedSos.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/sales/deliveries', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.deliveries.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load deliveries');
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
    this.loadConfirmedSos();
    this.formData = {
      so_id: null,
      warehouse_id: this.warehouses()[0]?.id || null,
      delivery_date: new Date().toISOString().substring(0, 10),
      notes: '',
      items: []
    };
    this.modalOpen.set(true);
  }

  onSoSelect() {
    if (!this.formData.so_id) {
      this.formData.items = [];
      return;
    }
    this.api.get<any>(`/sales/orders/${this.formData.so_id}`).subscribe({
      next: (res) => {
        const so = res?.data || res;
        this.formData.items = (so.items || []).map((it: any) => ({
          product_id: it.product_id,
          product_name: it.product_name,
          quantity_ordered: it.quantity,
          previously_delivered: it.delivered_qty || 0,
          quantity_delivered: Math.max(0, it.quantity - (it.delivered_qty || 0))
        }));
      }
    });
  }

  save() {
    if (!this.formData.so_id || !this.formData.warehouse_id) {
      this.toast.error('Please select both SO and Warehouse');
      return;
    }
    const validItems = this.formData.items.filter((i: any) => i.quantity_delivered > 0);
    if (!validItems.length) {
      this.toast.error('At least one item must have quantity delivered > 0');
      return;
    }

    this.saving.set(true);
    const payload = {
      ...this.formData,
      items: validItems
    };

    this.api.post('/sales/deliveries', payload).subscribe({
      next: () => {
        this.toast.success('Delivery dispatched! Stock deducted.');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to dispatch delivery');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/sales/deliveries/${item.id}`).subscribe({
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
