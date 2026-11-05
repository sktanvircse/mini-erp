import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { StockLedgerEntry, Product, Warehouse } from '../../../core/models/models';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-stock-ledger',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header
      title="Stock Ledger"
      [breadcrumbs]="[{ label: 'Inventory' }, { label: 'Stock Ledger' }]"
    />

    <div class="card mb-4">
      <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[180px]">
          <label class="form-label">Product</label>
          <select class="form-input" [(ngModel)]="filters.product_id" (change)="load()">
            <option value="">All Products</option>
            @for (p of products(); track p.id) {
              <option [value]="p.id">{{ p.code }} - {{ p.name }}</option>
            }
          </select>
        </div>
        <div class="flex-1 min-w-[180px]">
          <label class="form-label">Warehouse</label>
          <select class="form-input" [(ngModel)]="filters.warehouse_id" (change)="load()">
            <option value="">All Warehouses</option>
            @for (w of warehouses(); track w.id) {
              <option [value]="w.id">{{ w.name }}</option>
            }
          </select>
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="form-label">From Date</label>
          <input type="date" class="form-input" [(ngModel)]="filters.from_date" (change)="load()" />
        </div>
        <div class="flex-1 min-w-[150px]">
          <label class="form-label">To Date</label>
          <input type="date" class="form-input" [(ngModel)]="filters.to_date" (change)="load()" />
        </div>
        <div class="flex items-end">
          <button class="btn btn-secondary" (click)="clearFilters()">Clear</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Date</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Transaction Type</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Reference</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Qty In</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Qty Out</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Balance</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Unit Cost</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr>
                <td colspan="7" class="text-center py-8 text-gray-400">Loading...</td>
              </tr>
            } @else if (entries().length === 0) {
              <tr>
                <td colspan="7" class="text-center py-8 text-gray-400">No ledger entries found.</td>
              </tr>
            } @else {
              @for (entry of entries(); track entry.id) {
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 whitespace-nowrap">{{ entry.created_at | date:'yyyy-MM-dd' }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                      {{ entry.transaction_type }}
                    </span>
                  </td>
                  <td class="px-4 py-3 font-mono text-xs">{{ entry.reference_number }}</td>
                  <td class="px-4 py-3 text-right text-green-600 font-medium">
                    @if (entry.quantity_in > 0) {
                      {{ entry.quantity_in | number:'1.2-2' }}
                    } @else {
                      —
                    }
                  </td>
                  <td class="px-4 py-3 text-right text-red-600 font-medium">
                    @if (entry.quantity_out > 0) {
                      {{ entry.quantity_out | number:'1.2-2' }}
                    } @else {
                      —
                    }
                  </td>
                  <td class="px-4 py-3 text-right font-semibold">{{ entry.balance | number:'1.2-2' }}</td>
                  <td class="px-4 py-3 text-right text-gray-500">{{ entry.unit_cost | number:'1.2-2' }}</td>
                </tr>
              }
            }
          </tbody>
        </table>
      </div>

      @if (!loading()) {
        <div class="mt-4 text-sm text-gray-500">{{ entries().length }} entries</div>
      }

      <!-- Pagination -->
      @if (total() > perPage()) {
        <div class="flex justify-between items-center mt-4">
          <span class="text-sm text-gray-500">Page {{ page() }} of {{ lastPage() }}</span>
          <div class="flex gap-2">
            <button class="btn btn-secondary" [disabled]="page() === 1" (click)="changePage(page() - 1)">Previous</button>
            <button class="btn btn-secondary" [disabled]="page() === lastPage()" (click)="changePage(page() + 1)">Next</button>
          </div>
        </div>
      }
    </div>
  `
})
export class StockLedgerComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  entries = signal<StockLedgerEntry[]>([]);
  products = signal<Product[]>([]);
  warehouses = signal<Warehouse[]>([]);
  loading = signal(false);
  total = signal(0);
  page = signal(1);
  perPage = signal(50);
  lastPage = signal(1);

  filters = {
    product_id: '',
    warehouse_id: '',
    from_date: '',
    to_date: ''
  };

  ngOnInit(): void {
    this.loadReferenceData();
    this.load();
  }

  load(): void {
    this.loading.set(true);
    const params: Record<string, string> = { page: String(this.page()), per_page: String(this.perPage()) };
    if (this.filters.product_id) params['product_id'] = this.filters.product_id;
    if (this.filters.warehouse_id) params['warehouse_id'] = this.filters.warehouse_id;
    if (this.filters.from_date) params['from_date'] = this.filters.from_date;
    if (this.filters.to_date) params['to_date'] = this.filters.to_date;

    this.api.get<{ data: StockLedgerEntry[]; total: number; last_page: number }>('/inventory/ledger', params).subscribe({
      next: (resp) => {
        this.entries.set(resp.data ?? []);
        this.total.set(resp.total ?? 0);
        this.lastPage.set(resp.last_page ?? 1);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load stock ledger.');
        this.loading.set(false);
      }
    });
  }

  loadReferenceData(): void {
    this.api.get<any>('/products').subscribe({
      next: (res) => {
        const d = Array.isArray(res) ? res : (res?.data || []);
        this.products.set(d);
      },
      error: () => this.toast.error('Failed to load products.')
    });
    this.api.get<any>('/warehouses').subscribe({
      next: (res) => {
        const d = Array.isArray(res) ? res : (res?.data || []);
        this.warehouses.set(d);
      },
      error: () => this.toast.error('Failed to load warehouses.')
    });
  }

  clearFilters(): void {
    this.filters = { product_id: '', warehouse_id: '', from_date: '', to_date: '' };
    this.page.set(1);
    this.load();
  }

  changePage(p: number): void {
    this.page.set(p);
    this.load();
  }
}
