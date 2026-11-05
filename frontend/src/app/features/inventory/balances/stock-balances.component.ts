import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { StockBalance, Warehouse } from '../../../core/models/models';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-stock-balances',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header
      title="Stock Balances"
      [breadcrumbs]="[{ label: 'Inventory' }, { label: 'Stock Balances' }]"
    />

    <div class="card">
      <!-- Filters -->
      <div class="flex flex-wrap gap-4 mb-4">
        <div class="flex-1 min-w-[200px]">
          <label class="form-label">Warehouse</label>
          <select class="form-input" [(ngModel)]="selectedWarehouseId" (change)="onFilterChange()">
            <option value="">All Warehouses</option>
            @for (w of warehouses(); track w.id) {
              <option [value]="w.id">{{ w.name }}</option>
            }
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="form-label">Product Search</label>
          <input
            type="text"
            class="form-input"
            placeholder="Search product..."
            [(ngModel)]="productSearch"
            (input)="onFilterChange()"
          />
        </div>
        <div class="flex items-end">
          <button
            class="btn"
            [class.btn-danger]="lowStockMode()"
            [class.btn-secondary]="!lowStockMode()"
            (click)="toggleLowStock()"
          >
            @if (lowStockMode()) {
              ✓ Low Stock Only
            } @else {
              Show Low Stock
            }
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Product Code</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Product Name</th>
              <th class="text-left px-4 py-3 font-semibold text-gray-600">Warehouse</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Quantity</th>
              <th class="text-right px-4 py-3 font-semibold text-gray-600">Reorder Level</th>
              <th class="text-center px-4 py-3 font-semibold text-gray-600">Status</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr>
                <td colspan="6" class="text-center py-8 text-gray-400">Loading...</td>
              </tr>
            } @else if (balances().length === 0) {
              <tr>
                <td colspan="6" class="text-center py-8 text-gray-400">No stock balances found.</td>
              </tr>
            } @else {
              @for (item of filteredBalances(); track item.product_id + '-' + item.warehouse_id) {
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 font-mono text-xs">{{ item.product_code }}</td>
                  <td class="px-4 py-3">{{ item.product_name }}</td>
                  <td class="px-4 py-3">{{ item.warehouse_name }}</td>
                  <td class="px-4 py-3 text-right font-medium" [class.text-red-600]="item.quantity <= item.reorder_level">
                    {{ item.quantity | number:'1.2-2' }}
                  </td>
                  <td class="px-4 py-3 text-right text-gray-500">{{ item.reorder_level | number:'1.2-2' }}</td>
                  <td class="px-4 py-3 text-center">
                    @if (item.quantity <= item.reorder_level) {
                      <span class="badge-danger inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                        Low Stock
                      </span>
                    } @else {
                      <span class="badge-success inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                        OK
                      </span>
                    }
                  </td>
                </tr>
              }
            }
          </tbody>
        </table>
      </div>

      <!-- Summary -->
      @if (!loading()) {
        <div class="mt-4 text-sm text-gray-500">
          Showing {{ filteredBalances().length }} of {{ balances().length }} items
          @if (lowStockMode()) {
            &nbsp;(low stock filter active)
          }
        </div>
      }
    </div>
  `
})
export class StockBalancesComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  balances = signal<StockBalance[]>([]);
  warehouses = signal<Warehouse[]>([]);
  loading = signal(false);
  lowStockMode = signal(false);

  selectedWarehouseId = '';
  productSearch = '';

  ngOnInit(): void {
    this.loadWarehouses();
    this.load();
  }

  load(): void {
    this.loading.set(true);
    const endpoint = this.lowStockMode() ? '/inventory/low-stock' : '/inventory/balances';
    const params: Record<string, string> = {};
    if (this.selectedWarehouseId) params['warehouse_id'] = this.selectedWarehouseId;

    this.api.get<any>(endpoint, params).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.balances.set(data);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load stock balances.');
        this.loading.set(false);
      }
    });
  }

  loadWarehouses(): void {
    this.api.get<any>('/warehouses').subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.warehouses.set(data);
      },
      error: () => this.toast.error('Failed to load warehouses.')
    });
  }

  filteredBalances(): StockBalance[] {
    const search = this.productSearch.toLowerCase();
    return this.balances().filter(b => {
      const matchesSearch = !search ||
        b.product_name.toLowerCase().includes(search) ||
        b.product_code.toLowerCase().includes(search);
      return matchesSearch;
    });
  }

  onFilterChange(): void {
    this.load();
  }

  toggleLowStock(): void {
    this.lowStockMode.set(!this.lowStockMode());
    this.load();
  }
}
