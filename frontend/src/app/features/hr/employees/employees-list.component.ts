import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-employees-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Employees Directory" [breadcrumbs]="[{ label: 'HR' }, { label: 'Employees' }]">
      <button class="btn btn-primary" (click)="openModal(null)">+ Add Employee</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="employees()"
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
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentItem ? 'Edit Employee' : 'New Employee' }}</h3>
          <form (ngSubmit)="save()">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Employee Code *</label>
                <input type="text" [(ngModel)]="formData.employee_code" name="code" required class="form-input" placeholder="EMP-001" />
              </div>
              <div>
                <label class="form-label">Full Name *</label>
                <input type="text" [(ngModel)]="formData.name" name="name" required class="form-input" placeholder="John Doe" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Email</label>
                <input type="email" [(ngModel)]="formData.email" name="email" class="form-input" />
              </div>
              <div>
                <label class="form-label">Phone</label>
                <input type="text" [(ngModel)]="formData.phone" name="phone" class="form-input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Department *</label>
                <select [(ngModel)]="formData.department_id" (change)="onDepartmentChange()" name="department_id" required class="form-input">
                  <option [ngValue]="null">Select Dept</option>
                  @for (d of departments(); track d.id) {
                    <option [value]="d.id">{{ d.name }}</option>
                  }
                </select>
              </div>
              <div>
                <label class="form-label">Designation</label>
                <select [(ngModel)]="formData.designation_id" name="designation_id" class="form-input">
                  <option [ngValue]="null">Select Designation</option>
                  @for (ds of filteredDesignations(); track ds.id) {
                    <option [value]="ds.id">{{ ds.name }}</option>
                  }
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">Joining Date *</label>
                <input type="date" [(ngModel)]="formData.join_date" name="join_date" required class="form-input" />
              </div>
              <div>
                <label class="form-label">Basic Salary *</label>
                <input type="number" [(ngModel)]="formData.basic_salary" name="basic_salary" required min="0" step="0.01" class="form-input" />
              </div>
            </div>

            <div class="mb-6">
              <label class="form-label">Status *</label>
              <select [(ngModel)]="formData.status" name="status" required class="form-input">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="terminated">Terminated</option>
              </select>
            </div>

            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Saving...' : 'Save Employee' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class EmployeesListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  employees = signal<any[]>([]);
  departments = signal<any[]>([]);
  allDesignations = signal<any[]>([]);
  filteredDesignations = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 15;
  loading = signal(false);
  modalOpen = signal(false);
  saving = signal(false);

  searchQuery = '';
  currentItem: any = null;

  formData: any = {
    employee_code: '',
    name: '',
    email: '',
    phone: '',
    department_id: null,
    designation_id: null,
    join_date: new Date().toISOString().substring(0, 10),
    basic_salary: 0,
    status: 'active'
  };

  columns = [
    { key: 'employee_code', label: 'Code', sortable: true },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'department_name', label: 'Department' },
    { key: 'designation_name', label: 'Designation' },
    { key: 'basic_salary', label: 'Basic' },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.loadDepartments();
    this.loadDesignations();
    this.loadData();
  }

  loadDepartments() {
    this.api.get<any>('/hr/departments').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.departments.set(list);
      }
    });
  }

  loadDesignations() {
    this.api.get<any>('/hr/designations').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.allDesignations.set(list);
      }
    });
  }

  onDepartmentChange() {
    if (!this.formData.department_id) {
      this.filteredDesignations.set(this.allDesignations());
    } else {
      this.filteredDesignations.set(
        this.allDesignations().filter((d: any) => !d.department_id || d.department_id === this.formData.department_id)
      );
    }
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/hr/employees', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.employees.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load employees');
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
        employee_code: item.employee_code,
        name: item.name,
        email: item.email || '',
        phone: item.phone || '',
        department_id: item.department_id,
        designation_id: item.designation_id,
        join_date: item.join_date,
        basic_salary: item.basic_salary,
        status: item.status || 'active'
      };
      this.onDepartmentChange();
    } else {
      this.formData = {
        employee_code: '',
        name: '',
        email: '',
        phone: '',
        department_id: this.departments()[0]?.id || null,
        designation_id: null,
        join_date: new Date().toISOString().substring(0, 10),
        basic_salary: 0,
        status: 'active'
      };
      this.onDepartmentChange();
    }
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.employee_code || !this.formData.name || !this.formData.department_id) {
      this.toast.error('Code, Name, and Department are required');
      return;
    }
    this.saving.set(true);

    if (this.currentItem) {
      this.api.put(`/hr/employees/${this.currentItem.id}`, this.formData).subscribe({
        next: () => {
          this.toast.success('Employee updated');
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
      this.api.post('/hr/employees', this.formData).subscribe({
        next: () => {
          this.toast.success('Employee created');
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
