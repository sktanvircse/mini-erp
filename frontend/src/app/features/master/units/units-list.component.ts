import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { ConfirmDialogComponent } from '../../../shared/components/confirm-dialog/confirm-dialog.component';

@Component({
  selector: 'app-units-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, ConfirmDialogComponent],
  template: `
    <app-page-header title="Units of Measurement" [breadcrumbs]="[{ label: 'Master Data' }, { label: 'Units' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add Unit</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="units()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
      />
    </div>

    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentItem ? 'Edit Unit' : 'New Unit' }}</h3>
          <form (ngSubmit)="save()">
            <div class="mb-4">
              <label class="form-label">Unit Name *</label>
              <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" placeholder="e.g. Kilogram" />
            </div>
            <div class="mb-6">
              <label class="form-label">Symbol *</label>
              <input type="text" [(ngModel)]="formData.symbol" name="symbol" required class="form-input" placeholder="e.g. kg, pcs, box" />
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Save Unit' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <app-confirm-dialog
      [open]="deleteDialogOpen()"
      title="Delete Unit"
      message="Are you sure you want to delete this unit?"
      confirmLabel="Delete"
      [danger]="true"
      (confirm)="confirmDelete()"
      (cancel)="deleteDialogOpen.set(false)"
    />
  `
})
export class UnitsListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  units = signal<any[]>([]);
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
    symbol: ''
  };

  columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'symbol', label: 'Symbol', sortable: true }
  ];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/units', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.units.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load units');
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
      this.formData = { name: item.name, symbol: item.symbol };
    } else {
      this.formData = { name: '', symbol: '' };
    }
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.name || !this.formData.symbol) {
      this.toast.error('Both name and symbol are required');
      return;
    }
    this.saving.set(true);
    if (this.currentItem) {
      this.api.put(`/units/${this.currentItem.id}`, this.formData).subscribe({
        next: () => {
          this.toast.success('Unit updated successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to update unit');
          this.saving.set(false);
        }
      });
    } else {
      this.api.post('/units', this.formData).subscribe({
        next: () => {
          this.toast.success('Unit created successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to create unit');
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
    this.api.delete(`/units/${this.itemToDelete.id}`).subscribe({
      next: () => {
        this.toast.success('Unit deleted successfully');
        this.deleteDialogOpen.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Failed to delete unit');
        this.deleteDialogOpen.set(false);
      }
    });
  }
}
