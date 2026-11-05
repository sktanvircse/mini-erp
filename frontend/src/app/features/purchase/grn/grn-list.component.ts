import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-grn-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Goods Receipt Notes (GRN)" [breadcrumbs]="[{ label: 'Purchase' }, { label: 'GRN' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Goods Receipt</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="grns()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Goods Receipt</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Purchase Order *</label>
                <select [(ngModel)]="formData.po_id" (change)="onPoSelect()" name="po_id" required class="form-input">
                  <option [ngValue]="null">Select Approved PO</option>
                  @for (po of approvedPos(); track po.id) {
                    <option [value]="po.id">{{ po.po_number }} - {{ po.supplier_name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Destination Warehouse *</label>
                <select [(ngModel)]="formData.warehouse_id" name="warehouse_id" required class="form-input">
                  <option [ngValue]="null">Select Warehouse</option>
                  @for (w of warehouses(); track w.id) {
                    <option [value]="w.id">{{ w.name }} ({{ w.code }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Receipt Date *</label>
                <input type="date" [(ngModel)]="formData.receipt_date" name="receipt_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Notes</label>
              <input type="text" [(ngModel)]="formData.notes" name="notes" class="form-input" placeholder="Delivery vehicle, invoice ref..." />
            </div>

            <div class="mb-4">
              <h4 class="font-medium text-gray-700 text-sm mb-2">Items to Receive</h4>
              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right">Ordered</th>
                      <th class="p-2 text-right">Previously Received</th>
                      <th class="p-2 text-right w-24">Receiving Now</th>
                      <th class="p-2 text-right">Unit Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if (!formData.items.length) {
                      <tr><td colspan="5" class="p-4 text-center text-gray-400">Select a Purchase Order to load items</td></tr>
                    }
                    @for (line of formData.items; track $index) {
                      <tr class="border-b last:border-0">
                        <td class="p-2 font-medium">{{ line.product_name }}</td>
                        <td class="p-2 text-right">{{ line.quantity_ordered }}</td>
                        <td class="p-2 text-right">{{ line.previously_received || 0 }}</td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.quantity_received" name="rcv_{{$index}}" min="0" [max]="line.quantity_ordered - (line.previously_received || 0)" required class="form-input text-xs text-right" />
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
                {{ saving() ? 'Posting...' : 'Post Goods Receipt (GRN)' }}
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.grn_number }}</h3>
              <p class="text-xs text-gray-500">PO: {{ selectedItem.po_number }} | Warehouse: {{ selectedItem.warehouse_name }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Received Qty</th>
                  <th class="p-2 text-right">Unit Price</th>
                  <th class="p-2 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right font-semibold">{{ item.quantity_received }}</td>
                    <td class="p-2 text-right">{{ item.unit_price | number:'1.2-2' }}</td>
                    <td class="p-2 text-right font-medium">{{ item.total_price | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="flex justify-between items-center text-sm font-semibold border-t pt-3">
            <span>Total Value: {{ selectedItem.total_amount | number:'1.2-2' }}</span>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class GrnListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  grns = signal<any[]>([]);
  approvedPos = signal<any[]>([]);
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
    po_id: null,
    warehouse_id: null,
    receipt_date: new Date().toISOString().substring(0, 10),
    notes: '',
    items: []
  };

  columns = [
    { key: 'grn_number', label: 'GRN #', sortable: true },
    { key: 'po_number', label: 'PO #' },
    { key: 'supplier_name', label: 'Supplier' },
    { key: 'warehouse_name', label: 'Warehouse' },
    { key: 'receipt_date', label: 'Date', sortable: true },
    { key: 'total_amount', label: 'Total Value' }
  ];

  ngOnInit() {
    this.loadWarehouses();
    this.loadApprovedPos();
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

  loadApprovedPos() {
    this.api.get<any>('/purchase/orders', { status: 'approved' }).subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.approvedPos.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/purchase/grn', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.grns.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load GRNs');
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
    this.loadApprovedPos();
    this.formData = {
      po_id: null,
      warehouse_id: this.warehouses()[0]?.id || null,
      receipt_date: new Date().toISOString().substring(0, 10),
      notes: '',
      items: []
    };
    this.modalOpen.set(true);
  }

  onPoSelect() {
    if (!this.formData.po_id) {
      this.formData.items = [];
      return;
    }
    this.api.get<any>(`/purchase/orders/${this.formData.po_id}`).subscribe({
      next: (res) => {
        const po = res?.data || res;
        this.formData.items = (po.items || []).map((it: any) => ({
          product_id: it.product_id,
          product_name: it.product_name,
          quantity_ordered: it.quantity,
          previously_received: it.received_qty || 0,
          quantity_received: Math.max(0, it.quantity - (it.received_qty || 0)),
          unit_price: it.unit_price
        }));
      }
    });
  }

  save() {
    if (!this.formData.po_id || !this.formData.warehouse_id) {
      this.toast.error('Please select both PO and Warehouse');
      return;
    }
    const validItems = this.formData.items.filter((i: any) => i.quantity_received > 0);
    if (!validItems.length) {
      this.toast.error('At least one item must have quantity received > 0');
      return;
    }

    this.saving.set(true);
    const payload = {
      ...this.formData,
      items: validItems
    };

    this.api.post('/purchase/grn', payload).subscribe({
      next: () => {
        this.toast.success('Goods Receipt processed! Stock and Payable Ledger updated.');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to process GRN');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/purchase/grn/${item.id}`).subscribe({
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
