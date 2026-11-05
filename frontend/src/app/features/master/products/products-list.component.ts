import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';
import { ConfirmDialogComponent } from '../../../shared/components/confirm-dialog/confirm-dialog.component';
import { ProductFormComponent } from './product-form.component';

export interface Product {
  id: number;
  code: string;
  name: string;
  category: string;
  category_id: number;
  unit: string;
  unit_id: number;
  purchase_price: number;
  sale_price: number;
  reorder_level: number;
  is_active: boolean;
  description?: string;
}

@Component({
  selector: 'app-products-list',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    DataTableComponent,
    PageHeaderComponent,
    StatusBadgeComponent,
    ConfirmDialogComponent,
    ProductFormComponent,
  ],
  template: `
    <app-page-header
      title="Products"
      [breadcrumbs]="[{ label: 'Master Data' }, { label: 'Products' }]"
    />

    <div class="p-6">
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-700">Product List</h2>
          <button class="btn btn-primary" (click)="openForm(null)">
            <span class="mr-1">+</span> New Product
          </button>
        </div>

        <app-data-table
          [columns]="columns"
          [rows]="products()"
          [total]="total()"
          [page]="page()"
          [perPage]="perPage()"
          [loading]="loading()"
          (pageChange)="onPageChange($event)"
          (searchChange)="onSearch($event)"
          (rowClick)="openForm($event)"
        >
          <!-- Custom cell templates via row rendering -->
        </app-data-table>
      </div>

      <!-- Products Table (manual if DataTable doesn't support custom cells) -->
      @if (!loading() && products().length > 0) {
        <div class="card mt-4 overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Purchase Price</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Sale Price</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider">Reorder Level</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              @for (product of products(); track product.id) {
                <tr class="hover:bg-gray-50 cursor-pointer">
                  <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ product.code }}</td>
                  <td class="px-4 py-3 font-medium text-gray-900">{{ product.name }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ product.category }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ product.unit }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(product.purchase_price) }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ formatCurrency(product.sale_price) }}</td>
                  <td class="px-4 py-3 text-right text-gray-700">{{ product.reorder_level }}</td>
                  <td class="px-4 py-3">
                    <app-status-badge [status]="product.is_active ? 'active' : 'inactive'" />
                  </td>
                  <td class="px-4 py-3 text-center space-x-2">
                    <button class="btn btn-secondary text-xs py-1 px-2" (click)="openForm(product); $event.stopPropagation()">Edit</button>
                    <button class="btn btn-danger text-xs py-1 px-2" (click)="confirmDelete(product); $event.stopPropagation()">Delete</button>
                  </td>
                </tr>
              }
            </tbody>
          </table>

          <!-- Pagination -->
          <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200">
            <p class="text-sm text-gray-600">
              Showing {{ (page() - 1) * perPage() + 1 }} – {{ Math.min(page() * perPage(), total()) }} of {{ total() }} results
            </p>
            <div class="flex gap-2">
              <button class="btn btn-secondary text-xs" [disabled]="page() <= 1" (click)="onPageChange(page() - 1)">Previous</button>
              <button class="btn btn-secondary text-xs" [disabled]="page() * perPage() >= total()" (click)="onPageChange(page() + 1)">Next</button>
            </div>
          </div>
        </div>
      }

      @if (!loading() && products().length === 0) {
        <div class="text-center py-12 text-gray-500">
          <p class="text-lg">No products found.</p>
          <button class="btn btn-primary mt-4" (click)="openForm(null)">Add First Product</button>
        </div>
      }
    </div>

    <!-- Product Form Modal -->
    <app-product-form
      [open]="formOpen()"
      [product]="selectedProduct()"
      (saved)="onSaved()"
      (closed)="formOpen.set(false)"
    />

    <!-- Delete Confirm Dialog -->
    <app-confirm-dialog
      [open]="deleteOpen()"
      title="Delete Product"
      [message]="'Are you sure you want to delete product &quot;' + (selectedProduct()?.name ?? '') + '&quot;? This cannot be undone.'"
      confirmLabel="Delete"
      [danger]="true"
      (confirm)="doDelete()"
      (cancel)="deleteOpen.set(false)"
    />
  `,
})
export class ProductsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  products = signal<Product[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = signal(15);
  loading = signal(false);
  search = signal('');

  formOpen = signal(false);
  deleteOpen = signal(false);
  selectedProduct = signal<Product | null>(null);

  Math = Math;

  columns = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'unit', label: 'Unit' },
    { key: 'purchase_price', label: 'Purchase Price' },
    { key: 'sale_price', label: 'Sale Price' },
    { key: 'reorder_level', label: 'Reorder Level' },
    { key: 'is_active', label: 'Status' },
  ];

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.api
      .getList<Product>('/products', {
        page: this.page(),
        per_page: this.perPage(),
        search: this.search(),
      })
      .subscribe({
        next: (res: any) => {
          this.products.set(res.data ?? res.results ?? res ?? []);
          this.total.set(res.total ?? res.count ?? 0);
          this.loading.set(false);
        },
        error: (err: any) => {
          this.toast.error('Failed to load products');
          this.loading.set(false);
        },
      });
  }

  onPageChange(p: number): void {
    this.page.set(p);
    this.load();
  }

  onSearch(q: string): void {
    this.search.set(q);
    this.page.set(1);
    this.load();
  }

  openForm(product: Product | null): void {
    this.selectedProduct.set(product);
    this.formOpen.set(true);
  }

  confirmDelete(product: Product): void {
    this.selectedProduct.set(product);
    this.deleteOpen.set(true);
  }

  doDelete(): void {
    const p = this.selectedProduct();
    if (!p) return;
    this.api.delete(`/products/${p.id}`).subscribe({
      next: () => {
        this.toast.success('Product deleted successfully');
        this.deleteOpen.set(false);
        this.selectedProduct.set(null);
        this.load();
      },
      error: () => {
        this.toast.error('Failed to delete product');
        this.deleteOpen.set(false);
      },
    });
  }

  onSaved(): void {
    this.formOpen.set(false);
    this.selectedProduct.set(null);
    this.load();
  }

  formatCurrency(val: number): string {
    return new Intl.NumberFormat('en-US', { style: 'decimal', minimumFractionDigits: 2 }).format(val ?? 0);
  }
}
