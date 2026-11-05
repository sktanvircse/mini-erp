import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-users-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="User Management" [breadcrumbs]="[{ label: 'Admin' }, { label: 'Users' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add User</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="users()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
        (rowClick)="openModal($event)"
      />
    </div>

    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentUser ? 'Edit User' : 'New User' }}</h3>
          <form (ngSubmit)="save()">
            <div class="mb-4">
              <label class="form-label">Full Name *</label>
              <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" />
            </div>
            <div class="mb-4">
              <label class="form-label">Email Address *</label>
              <input type="email" [(ngModel)]="formData.email" name="email" required class="form-input" />
            </div>
            @if (!currentUser) {
              <div class="mb-4">
                <label class="form-label">Password *</label>
                <input type="password" [(ngModel)]="formData.password" name="password" required class="form-input" />
              </div>
            }
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Role *</label>
                <select [(ngModel)]="formData.role_id" name="role_id" required class="form-input">
                  <option [ngValue]="null">Select Role</option>
                  @for (r of roles(); track r.id) {
                    <option [value]="r.id">{{ r.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Branch *</label>
                <select [(ngModel)]="formData.branch_id" name="branch_id" required class="form-input">
                  <option [ngValue]="null">Select Branch</option>
                  @for (b of branches(); track b.id) {
                    <option [value]="b.id">{{ b.name }}</option>
                  }
                </select>
              </div>
            </div>
            <div class="mb-6 flex items-center">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" [(ngModel)]="formData.is_active" name="is_active" class="rounded text-blue-600" />
                <span class="text-sm font-medium text-gray-700">Account Active</span>
              </label>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">Save User</button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class UsersListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  users = signal<any[]>([]);
  roles = signal<any[]>([]);
  branches = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  currentUser: any = null;

  formData: any = {
    name: '',
    email: '',
    password: '',
    role_id: null,
    branch_id: null,
    is_active: true
  };

  columns = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'role_name', label: 'Role' },
    { key: 'branch_name', label: 'Branch' },
    { key: 'status_label', label: 'Status' }
  ];

  ngOnInit() {
    this.loadRoles();
    this.loadBranches();
    this.loadData();
  }

  loadRoles() {
    this.api.get<any>('/admin/roles').subscribe({
      next: (res) => this.roles.set(Array.isArray(res) ? res : (res?.data || []))
    });
  }

  loadBranches() {
    this.api.get<any>('/branches').subscribe({
      next: (res) => this.branches.set(Array.isArray(res) ? res : (res?.data || []))
    });
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/admin/users', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        const mapped = data.map((d: any) => ({
          ...d,
          status_label: d.is_active ? 'Active' : 'Inactive'
        }));
        this.users.set(mapped);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load users');
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
    this.currentUser = item;
    if (item) {
      this.formData = {
        name: item.name,
        email: item.email,
        role_id: item.role_id,
        branch_id: item.branch_id,
        is_active: Boolean(item.is_active)
      };
    } else {
      this.formData = {
        name: '',
        email: '',
        password: '',
        role_id: this.roles()[0]?.id || null,
        branch_id: this.branches()[0]?.id || null,
        is_active: true
      };
    }
    this.modalOpen.set(true);
  }

  save() {
    this.saving.set(true);
    const payload = {
      ...this.formData,
      is_active: this.formData.is_active ? 1 : 0
    };

    if (this.currentUser) {
      this.api.put(`/admin/users/${this.currentUser.id}`, payload).subscribe({
        next: () => {
          this.toast.success('User updated');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Update failed');
          this.saving.set(false);
        }
      });
    } else {
      this.api.post('/admin/users', payload).subscribe({
        next: () => {
          this.toast.success('User created');
          this.modalOpen.set(false);
          this.saving.set(false);
          this.loadData();
        },
        error: (e) => {
          this.toast.error(e?.error?.message || 'Creation failed');
          this.saving.set(false);
        }
      });
    }
  }
}
