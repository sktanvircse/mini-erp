import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-requisitions-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Purchase Requisitions" [breadcrumbs]="[{ label: 'Purchase' }, { label: 'Requisitions' }]">
      <button class="btn btn-primary" (click)="openModal()">+ New Requisition</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="requisitions()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create Purchase Requisition</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Required Date *</label>
                <input type="date" [(ngModel)]="formData.required_date" name="required_date" required class="form-input" />
              </div>
              <div>
                <label class="form-label">Notes</label>
                <input type="text" [(ngModel)]="formData.notes" name="notes" class="form-input" placeholder="Urgent request..." />
              </div>
            </div>

            <div class="mb-4">
              <div class="flex justify-between items-center mb-2">
                <h4 class="font-medium text-gray-700 text-sm">Item Lines</h4>
                <button type="button" class="btn btn-secondary btn-sm" (click)="addItemLine()">+ Add Item</button>
              </div>

              <div class="border rounded-lg overflow-x-auto">
                <table class="w-full text-xs">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="p-2 text-left">Product</th>
                      <th class="p-2 text-right w-24">Qty</th>
                      <th class="p-2 text-right w-28">Est. Price</th>
                      <th class="p-2 text-left">Notes</th>
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
                          <input type="number" [(ngModel)]="line.quantity" name="qty_{{$index}}" required min="1" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="number" [(ngModel)]="line.estimated_price" name="price_{{$index}}" min="0" step="0.01" class="form-input text-xs text-right" />
                        </td>
                        <td class="p-2">
                          <input type="text" [(ngModel)]="line.notes" name="notes_{{$index}}" class="form-input text-xs" />
                        </td>
                        <td class="p-2 text-center">
                          <button type="button" class="text-red-500 hover:text-red-700 font-bold" (click)="removeItemLine($index)">✕</button>
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
                {{ saving() ? 'Saving...' : 'Submit Requisition' }}
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.pr_number }}</h3>
              <p class="text-xs text-gray-500">Requested: {{ selectedItem.request_date }} | Required: {{ selectedItem.required_date }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="border rounded-lg overflow-hidden mb-6">
            <table class="w-full text-xs">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="p-2 text-left">Product</th>
                  <th class="p-2 text-right">Qty</th>
                  <th class="p-2 text-right">Est. Price</th>
                </tr>
              </thead>
              <tbody>
                @for (item of selectedItem.items; track item.id || $index) {
                  <tr class="border-b last:border-0">
                    <td class="p-2">{{ item.product_name }}</td>
                    <td class="p-2 text-right">{{ item.quantity }}</td>
                    <td class="p-2 text-right">{{ item.estimated_price | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="flex justify-between items-center">
            <div class="flex gap-2">
              @if (selectedItem.status === 'submitted' || selectedItem.status === 'draft') {
                <button class="btn btn-success btn-sm" (click)="approve(selectedItem.id)">Approve</button>
                <button class="btn btn-danger btn-sm" (click)="reject(selectedItem.id)">Reject</button>
              }
            </div>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class RequisitionsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  requisitions = signal<any[]>([]);
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
    required_date: new Date().toISOString().substring(0, 10),
    notes: '',
    items: []
  };

  columns = [
    { key: 'pr_number', label: 'PR #', sortable: true },
    { key: 'request_date', label: 'Date', sortable: true },
    { key: 'required_date', label: 'Required Date' },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadProducts();
    this.loadData();
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
    this.api.get<any>('/purchase/requisitions', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.requisitions.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load requisitions');
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
      required_date: new Date().toISOString().substring(0, 10),
      notes: '',
      items: [{ product_id: null, quantity: 1, estimated_price: 0, notes: '' }]
    };
    this.modalOpen.set(true);
  }

  addItemLine() {
    this.formData.items.push({ product_id: null, quantity: 1, estimated_price: 0, notes: '' });
  }

  removeItemLine(index: number) {
    if (this.formData.items.length > 1) {
      this.formData.items.splice(index, 1);
    }
  }

  save() {
    if (!this.formData.items.length || this.formData.items.some((i: any) => !i.product_id || i.quantity <= 0)) {
      this.toast.error('Please specify valid product and quantity for all items');
      return;
    }
    this.saving.set(true);
    this.api.post('/purchase/requisitions', this.formData).subscribe({
      next: () => {
        this.toast.success('Purchase requisition created');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to create requisition');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.api.get<any>(`/purchase/requisitions/${item.id}`).subscribe({
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

  approve(id: number) {
    this.api.patch(`/purchase/requisitions/${id}/approve`).subscribe({
      next: () => {
        this.toast.success('Requisition approved');
        this.detailOpen.set(false);
        this.loadData();
      },
      error: (e) => this.toast.error(e?.error?.message || 'Approval failed')
    });
  }

  reject(id: number) {
    this.api.patch(`/purchase/requisitions/${id}/reject`).subscribe({
      next: () => {
        this.toast.success('Requisition rejected');
        this.detailOpen.set(false);
        this.loadData();
      },
      error: (e) => this.toast.error(e?.error?.message || 'Rejection failed')
    });
  }
}
