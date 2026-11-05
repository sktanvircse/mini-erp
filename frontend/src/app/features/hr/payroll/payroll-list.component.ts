import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-payroll-list',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Payroll Processing" [breadcrumbs]="[{ label: 'HR' }, { label: 'Payroll' }]">
      <button class="btn btn-primary" (click)="openModal()">+ Generate Payroll</button>
    </app-page-header>

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="payrolls()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
        (rowClick)="viewDetail($event)"
      />
    </div>

    <!-- Generate Modal -->
    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate Monthly Payroll</h3>
          <form (ngSubmit)="generate()">
            <div class="mb-4">
              <label class="form-label">Employee</label>
              <select [(ngModel)]="genForm.employee_id" name="employee_id" class="form-input">
                <option [ngValue]="null">All Employees</option>
                @for (e of employees(); track e.id) {
                  <option [value]="e.id">{{ e.name }}</option>
                }
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
              <div>
                <label class="form-label">Month *</label>
                <select [(ngModel)]="genForm.month" name="month" required class="form-input">
                  <option [value]="1">January</option>
                  <option [value]="2">February</option>
                  <option [value]="3">March</option>
                  <option [value]="4">April</option>
                  <option [value]="5">May</option>
                  <option [value]="6">June</option>
                  <option [value]="7">July</option>
                  <option [value]="8">August</option>
                  <option [value]="9">September</option>
                  <option [value]="10">October</option>
                  <option [value]="11">November</option>
                  <option [value]="12">December</option>
                </select>
              </div>
              <div>
                <label class="form-label">Year *</label>
                <input type="number" [(ngModel)]="genForm.year" name="year" required class="form-input" />
              </div>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" [disabled]="saving()" class="btn btn-primary">
                {{ saving() ? 'Calculating...' : 'Run Payroll' }}
              </button>
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
              <h3 class="text-lg font-semibold text-gray-900">{{ selectedItem.payroll_number }}</h3>
              <p class="text-xs text-gray-500">{{ selectedItem.employee_name }} | Month: {{ selectedItem.month }}/{{ selectedItem.year }}</p>
            </div>
            <app-status-badge [status]="selectedItem.status" />
          </div>

          <div class="space-y-2 text-sm border-t border-b py-3 mb-4">
            <div class="flex justify-between">
              <span class="text-gray-500">Basic Salary:</span>
              <span>{{ selectedItem.basic_salary | number:'1.2-2' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Overtime Amount:</span>
              <span class="text-green-600">+ {{ (selectedItem.overtime_amount || 0) | number:'1.2-2' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Deductions (Absence/Tax):</span>
              <span class="text-red-600">- {{ (selectedItem.deductions || 0) | number:'1.2-2' }}</span>
            </div>
            <div class="flex justify-between font-bold border-t pt-2 text-base">
              <span>Net Payable:</span>
              <span class="text-blue-700">{{ selectedItem.net_salary | number:'1.2-2' }}</span>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <div>
              @if (selectedItem.status === 'draft') {
                <button class="btn btn-success btn-sm" (click)="approve(selectedItem.id)">Approve & Post Expense</button>
              }
            </div>
            <button class="btn btn-secondary btn-sm" (click)="detailOpen.set(false)">Close</button>
          </div>
        </div>
      </div>
    }
  `
})
export class PayrollListComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  payrolls = signal<any[]>([]);
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

  genForm = {
    employee_id: null,
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear()
  };

  columns = [
    { key: 'payroll_number', label: 'Slip #', sortable: true },
    { key: 'employee_name', label: 'Employee', sortable: true },
    { key: 'period_label', label: 'Period' },
    { key: 'basic_salary', label: 'Basic' },
    { key: 'net_salary', label: 'Net Pay' },
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
    this.api.get<any>('/hr/payroll', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        const mapped = data.map((d: any) => ({
          ...d,
          period_label: `${d.month}/${d.year}`
        }));
        this.payrolls.set(mapped);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load payroll');
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
    this.modalOpen.set(true);
  }

  generate() {
    this.saving.set(true);
    this.api.post('/hr/payroll/generate', this.genForm).subscribe({
      next: () => {
        this.toast.success('Payroll generated successfully!');
        this.modalOpen.set(false);
        this.saving.set(false);
        this.loadData();
      },
      error: (e) => {
        this.toast.error(e?.error?.message || 'Payroll generation failed');
        this.saving.set(false);
      }
    });
  }

  viewDetail(item: any) {
    this.selectedItem = item;
    this.detailOpen.set(true);
  }

  approve(id: number) {
    this.api.patch(`/hr/payroll/${id}/approve`).subscribe({
      next: () => {
        this.toast.success('Payroll approved & salary expense voucher posted to GL!');
        this.detailOpen.set(false);
        this.loadData();
      },
      error: (e) => this.toast.error(e?.error?.message || 'Approval failed')
    });
  }
}
