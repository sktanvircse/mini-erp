import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { ConfirmDialogComponent } from '../../../shared/components/confirm-dialog/confirm-dialog.component';

@Component({
  selector: 'app-categories-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, ConfirmDialogComponent],
  template: `
    <app-page-header title="Product Categories" [breadcrumbs]="[{ label: 'Master Data' }, { label: 'Categories' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add Category</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="categories()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
      >
        <div actions></div>
      </app-data-table>
    </div>

    <!-- Modal -->
    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentItem ? 'Edit Category' : 'New Category' }}</h3>
          <form (ngSubmit)="save()">
            <div class="mb-4">
              <label class="form-label">Category Name *</label>
              <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" placeholder="e.g. Electronics" />
            </div>
            <div class="mb-6">
              <label class="form-label">Description</label>
              <textarea [(ngModel)]="formData.description" name="description" rows="3" class="form-input" placeholder="Optional notes..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Save Category' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <app-confirm-dialog
      [open]="deleteDialogOpen()"
      title="Delete Category"
      message="Are you sure you want to delete this category?"
      confirmLabel="Delete"
      [danger]="true"
      (confirm)="confirmDelete()"
      (cancel)="deleteDialogOpen.set(false)"
    />
  `
})
export class CategoriesListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  categories = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  deleteDialogOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  currentItem: any = null;
  itemToDelete: any = null;

  formData = {
    name: '',
    description: ''
  };

  columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'description', label: 'Description' },
    { key: 'created_at', label: 'Created At' }
  ];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/categories', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.categories.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: (err) => {
        this.toast.error('Failed to load categories');
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

  openModal(item: any) {
    this.currentItem = item;
    if (item) {
      this.formData = { name: item.name, description: item.description || '' };
    } else {
      this.formData = { name: '', description: '' };
    }
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.name.trim()) {
      this.toast.error('Category name is required');
      return;
    }
    this.saving.set(true);
    if (this.currentItem) {
      this.api.put(`/categories/${this.currentItem.id}`, this.formData).subscribe({
        next: () => {
          this.toast.success('Category updated successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to update category');
          this.saving.set(false);
        }
      });
    } else {
      this.api.post('/categories', this.formData).subscribe({
        next: () => {
          this.toast.success('Category created successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to create category');
          this.saving.set(false);
        }
      });
    }
  }

  promptDelete(item: any) {
    this.itemToDelete = item;
    this.deleteDialogOpen.set(true);
  }

  confirmDelete() {
    if (!this.itemToDelete) return;
    this.api.delete(`/categories/${this.itemToDelete.id}`).subscribe({
      next: () => {
        this.toast.success('Category deleted successfully');
        this.deleteDialogOpen.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to delete category');
        this.deleteDialogOpen.set(false);
      }
    });
  }
}
