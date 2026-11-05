import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-leave-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Leave Requests" [breadcrumbs]="[{ label: 'HR' }, { label: 'Leave' }]">
      <button class="btn btn-primary" (click)="openModal()">+ Apply for Leave</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="requests()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
        (rowClick)="viewDetail($event)"
      />
    </div>

    <!-- Apply Modal -->
    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Submit Leave Request</h3>
          <form (ngSubmit)="save()">
            <div class="mb-4">
              <label class="form-label">Employee *</label>
              <select [(ngModel)]="formData.employee_id" name="employee_id" required class="form-input">
                <option [ngValue]="null">Select Employee</option>
                @for (e of employees(); track e.id) {
                  <option [value]="e.id">{{ e.name }}</option>
                }
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label">Leave Type *</label>
              <select [(ngModel)]="formData.leave_type" name="leave_type" required class="form-input">
                <option value="casual">Casual Leave</option>
                <option value="sick">Sick Leave</option>
                <option value="annual">Annual Leave</option>
                <option value="unpaid">Unpaid Leave</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <label class="form-label">From Date *</label>
                <input type="date" [(ngModel)]="formData.from_date" name="from_date" required class="form-input" />
              </div>
              <div>
                <label class="form-label">To Date *</label>
                <input type="date" [(ngModel)]="formData.to_date" name="to_date" required class="form-input" />
              </div>
            </div>
            <div class="mb-6">
              <label class="form-label">Reason *</label>
              <textarea [(ngModel)]="formData.reason" name="reason" rows="2" required class="form-input" placeholder="Doctor visit, family emergency..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">Submit Request</button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Detail Modal -->
    @if (detailOpen() && selectedItem) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-md w-full mx-4">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.employee_name }}</h3>
              <p class="text-xs text-gray-500 uppercase">{{ selectedItem.leave_type }} LEAVE</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="space-y-2 text-sm border-t border-b py-3 mb-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Duration:</span>
              <span class="font-medium">{{ selectedItem.from_date }} to {{ selectedItem.to_date }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Total Days:</span>
              <span class="font-bold">{{ selectedItem.days }} day(s)</span>
            </div>
            <div>
              <span class="text-gray-500 block mb-1">Reason:</span>
              <p class="text-gray-800 bg-gray-50 p-2 rounded text-xs">{{ selectedItem.reason }}</p>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <div class="flex gap-2">
              @if (selectedItem.status === 'pending') {
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
export class LeaveListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  requests = signal<any[]>([]);
  employees = signal<any[]>([]);
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
    employee_id: null,
    leave_type: 'casual',
    from_date: new Date().toISOString().substring(0, 10),
    to_date: new Date().toISOString().substring(0, 10),
    reason: ''
  };

  columns = [
    { key: 'employee_name', label: 'Employee', sortable: true },
    { key: 'leave_type', label: 'Type' },
    { key: 'from_date', label: 'From' },
    { key: 'to_date', label: 'To' },
    { key: 'days', label: 'Days' },
    { key: 'status', label: 'Status' }
  ];

  ngOnInit() {
    this.api.get<any>('/hr/employees').subscribe({
      next: (res) => this.employees.set(Array.isArray(res) ? res : (res?.data || []))
    });
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/hr/leave', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.requests.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load leave requests');
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
      employee_id: this.employees()[0]?.id || null,
      leave_type: 'casual',
      from_date: new Date().toISOString().substring(0, 10),
      to_date: new Date().toISOString().substring(0, 10),
      reason: ''
    };
    this.modalOpen.set(true);
  }

  save() {
    if (!this.formData.employee_id || !this.formData.reason.trim()) {
      this.toast.error('Employee and Reason are required');
      return;
    }
    this.saving.set(true);
    this.api.post('/hr/leave', this.formData).subscribe({
      next: () => {
        this.toast.success('Leave request submitted');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Submission failed');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.selectedItem = item;
    this.detailOpen.set(true);
  }

  approve(id: number) {
    this.api.patch(`/hr/leave/${id}/approve`).subscribe({
      next: () => {
        this.toast.success('Leave approved');
        this.detailOpen.set(false);
        this.loadData();
      }
    });
  }

  reject(id: number) {
    this.api.patch(`/hr/leave/${id}/reject`).subscribe({
      next: () => {
        this.toast.success('Leave rejected');
        this.detailOpen.set(false);
        this.loadData();
      }
    });
  }
}
