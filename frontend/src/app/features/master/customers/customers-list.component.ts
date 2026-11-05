import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-customers-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Customers" [breadcrumbs]="[{ label: 'Master Data' }, { label: 'Customers' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add Customer</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="customers()"
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
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentItem ? 'Edit Customer' : 'New Customer' }}</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Customer Code *</label>
                <input type="text" [(ngModel)]="formData.code" name="code" required class="form-input" placeholder="CUST-001" />
              </div>
              <div>
                <label class="form-label">Customer Name *</label>
                <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" placeholder="Acme Corp" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Contact Person</label>
                <input type="text" [(ngModel)]="formData.contact_person" name="contact_person" class="form-input" />
              </div>
              <div>
                <label class="form-label">Phone</label>
                <input type="text" [(ngModel)]="formData.phone" name="phone" class="form-input" />
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label">Email</label>
              <input type="email" [(ngModel)]="formData.email" name="email" class="form-input" />
            </div>

            <div class="mb-4">
              <label class="form-label">Address</label>
              <textarea [(ngModel)]="formData.address" name="address" rows="2" class="form-input"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-4">
              <div>
                <label class="form-label">Credit Limit</label>
                <input type="number" [(ngModel)]="formData.credit_limit" name="credit_limit" class="form-input" />
              </div>
              <div>
                <label class="form-label">Credit Days</label>
                <input type="number" [(ngModel)]="formData.credit_days" name="credit_days" class="form-input" />
              </div>
              <div>
                <label class="form-label">Opening Bal.</label>
                <input type="number" [(ngModel)]="formData.opening_balance" name="opening_balance" class="form-input" [disabled]="!!currentItem" />
              </div>
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
                {{ saving() ? 'Saving...' : 'Save Customer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class CustomersListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  customers = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  currentItem: any = null;

  formData = {
    code: '',
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    credit_limit: 0,
    credit_days: 0,
    opening_balance: 0,
    is_active: true
  };

  columns = [
    { key: 'code', label: 'Code', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'phone', label: 'Phone' },
    { key: 'credit_limit', label: 'Credit Limit' },
    { key: 'current_balance', label: 'Balance' },
    { key: 'status_label', label: 'Status' }
  ];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/customers', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        const mapped = data.map((d: any) => ({
          ...d,
          status_label: d.is_active ? 'Active' : 'Inactive'
        }));
        this.customers.set(mapped);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load customers');
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
        code: item.code,
        name: item.name,
        contact_person: item.contact_person || '',
        phone: item.phone || '',
        email: item.email || '',
        address: item.address || '',
        credit_limit: item.credit_limit || 0,
        credit_days: item.credit_days || 0,
        opening_balance: item.opening_balance || 0,
        is_active: Boolean(item.is_active)
      };
    } else {
      this.formData = {
        code: '',
        name: '',
        contact_person: '',
        phone: '',
        email: '',
        address: '',
        credit_limit: 0,
        credit_days: 0,
        opening_balance: 0,
        is_active: true
      };
    }
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.code || !this.formData.name) {
      this.toast.error('Code and Name are required');
      return;
    }
    this.saving.set(true);
    const payload = {
      ...this.formData,
      is_active: this.formData.is_active ? 1 : 0
    };

    if (this.currentItem) {
      this.api.put(`/customers/${this.currentItem.id}`, payload).subscribe({
        next: () => {
          this.toast.success('Customer updated successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to update customer');
          this.saving.set(false);
        }
      });
    } else {
      this.api.post('/customers', payload).subscribe({
        next: () => {
          this.toast.success('Customer created successfully');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Failed to create customer');
          this.saving.set(false);
        }
      });
    }
  }
}
