import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-returns-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Purchase Returns" [breadcrumbs]="[{ label: 'Purchase' }, { label: 'Returns' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Purchase Return</button>
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Purchase Return</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">GRN Reference *</label>
                <select [(ngModel)]="formData.grn_id" (change)="onGrnSelect()" name="grn_id" required class="form-input">
                  <option [ngValue]="null">Select GRN</option>
                  @for (g of grnList(); track g.id) {
                    <option [value]="g.id">{{ g.grn_number }} ({{ g.supplier_name }})</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Return Date *</label>
                <input type="date" [(ngModel)]="formData.return_date" name="return_date" required class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Reason for Return *</label>
              <input type="text" [(ngModel)]="formData.reason" name="reason" required class="form-input" placeholder="Defective, wrong specs..." />
            </div>

            <div class="mb-4">
              <h4 class="font-medium text-gray-700 text-sm mb-2">Items to Return</h4>
              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right">Received Qty</th>
                      <th class="p-2 text-right w-24">Return Qty</th>
                      <th class="p-2 text-right">Unit Price</th>
                    </tr>
                  </thead>
                  <tbody>
                    @if (!formData.items.length) {
                      <tr><td colspan="4" class="p-4 text-center text-gray-400">Select a GRN first</td></tr>
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
                {{ saving() ? 'Saving...' : 'Submit Return' }}
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
              <p class="text-xs text-gray-500">GRN: {{ selectedItem.grn_number }} | Reason: {{ selectedItem.reason }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-4">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Returned Qty</th>
                  <th class="p-2 text-right">Unit Price</th>
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
            <span>Total Returned Value: {{ selectedItem.total_amount | number:'1.2-2' }}</span>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class ReturnsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  returns = signal<any[]>([]);
  grnList = signal<any[]>([]);
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
    grn_id: null,
    warehouse_id: null,
    return_date: new Date().toISOString().substring(0, 10),
    reason: '',
    items: []
  };

  columns = [
    { key: 'return_number', label: 'Return #', sortable: true },
    { key: 'grn_number', label: 'GRN #' },
    { key: 'supplier_name', label: 'Supplier' },
    { key: 'return_date', label: 'Date', sortable: true },
    { key: 'total_amount', label: 'Total Value' }
  ];

  ngOnInit() {
    this.loadGrns();
    this.loadData();
  }

  loadGrns() {
    this.api.get<any>('/purchase/grn').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.grnList.set(list);
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/purchase/returns', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.returns.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load purchase returns');
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
    this.loadGrns();
    this.formData = {
      grn_id: null,
      warehouse_id: null,
      return_date: new Date().toISOString().substring(0, 10),
      reason: '',
      items: []
    };
    this.modalOpen.set(true);
  }

  onGrnSelect() {
    if (!this.formData.grn_id) {
      this.formData.items = [];
      return;
    }
    this.api.get<any>(`/purchase/grn/${this.formData.grn_id}`).subscribe({
      next: (res) => {
        const grn = res?.data || res;
        this.formData.warehouse_id = grn.warehouse_id;
        this.formData.items = (grn.items || []).map((it: any) => ({
          product_id: it.product_id,
          product_name: it.product_name,
          max_qty: it.quantity_received,
          quantity: 0,
          unit_price: it.unit_price
        }));
      }
    });
  }

  save() {
    if (!this.formData.grn_id || !this.formData.reason) {
      this.toast.error('GRN Reference and Reason are required');
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

    this.api.post('/purchase/returns', payload).subscribe({
      next: () => {
        this.toast.success('Purchase Return processed! AP & Stock reversed.');
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
    this.api.get<any>(`/purchase/returns/${item.id}`).subscribe({
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
