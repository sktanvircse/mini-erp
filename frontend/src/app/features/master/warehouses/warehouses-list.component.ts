import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-warehouses-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Warehouses" [breadcrumbs]="[{ label: 'Master Data' }, { label: 'Warehouses' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add Warehouse</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="warehouses()"
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
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentItem ? 'Edit Warehouse' : 'New Warehouse' }}</h3>
          <form (ngSubmit)="save()">
            <div class="mb-4">
              <label class="form-label">Branch *</label>
              <select [(ngModel)]="formData.branch_id" name="branch_id" required class="form-input">
                <option [ngValue]="null">Select Branch</option>
                @for (b of branches(); track b.id) {
                  <option [value]="b.id">{{ b.name }} ({{ b.code }})</option>
                }
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label">Warehouse Code *</label>
              <input type="text" [(ngModel)]="formData.code" name="code" required class="form-input" placeholder="WH-001" />
            </div>
            <div class="mb-4">
              <label class="form-label">Warehouse Name *</label>
              <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" placeholder="Main Warehouse" />
            </div>
            <div class="mb-4">
              <label class="form-label">Location / Address</label>
              <input type="text" [(ngModel)]="formData.location" name="location" class="form-input" placeholder="Building A, Floor 1" />
            </div>
            <div class="mb-6 flex items-center">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" [(ngModel)]="formData.is_active" name="is_active" class="rounded text-blue-600" />
                <span class="text-sm font-medium text-gray-700">Active</span>
              </label>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Save Warehouse' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class WarehousesListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  warehouses = signal<any[]>([]);
  branches = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  currentItem: any = null;

  formData = {
    branch_id: null,
    code: '',
    name: '',
    location: '',
    is_active: true
  };

  columns = [
    { key: 'code', label: 'Code', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'branch_name', label: 'Branch' },
    { key: 'location', label: 'Location' },
    { key: 'status_label', label: 'Status' }
  ];

  ngOnInit() {
    this.loadBranches();
    this.loadData();
  }

  loadBranches() {
    this.api.get<any>('/companies').subscribe({
      next: (res) => {
        // load branches if available
        this.api.get<any>('/branches').subscribe({
          next: (bRes) => {
            const list = Array.isArray(bRes) ? bRes : (bRes?.data || []);
            this.branches.set(list);
          },
          error: () => {}
        });
      }
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/warehouses', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        const mapped = data.map((d: any) => ({
          ...d,
          status_label: d.is_active ? 'Active' : 'Inactive'
        }));
        this.warehouses.set(mapped);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load warehouses');
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
      this.formData = {
        branch_id: item.branch_id,
        code: item.code,
        name: item.name,
        location: item.location || '',
        is_active: Boolean(item.is_active)
      };
    } else {
      this.formData = {
        branch_id: this.branches()[0]?.id || null,
        code: '',
        name: '',
        location: '',
        is_active: true
      };
    }
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.name || !this.formData.code) {
      this.toast.error('Code and Name are required');
      return;
    }
    this.saving.set(true);
    const payload = {
      ...this.formData,
      is_active: this.formData.is_active ? 1 : 0
    };

    if (this.currentItem) {
      this.api.put(`/warehouses/${this.currentItem.id}`, payload).subscribe({
        next: () => {
          this.toast.success('Warehouse updated successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to update warehouse');
          this.saving.set(false);
        }
      });
    } else {
      this.api.post('/warehouses', payload).subscribe({
        next: () => {
          this.toast.success('Warehouse created successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to create warehouse');
          this.saving.set(false);
        }
      });
    }
  }
}
