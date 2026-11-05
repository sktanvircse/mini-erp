import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

@Component({
  selector: 'app-attendance',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Attendance Management" [breadcrumbs]="[{ label: 'HR' }, { label: 'Attendance' }]">
      <div class="flex gap-2">
        <button class="btn" [class.btn-primary]="activeTab === 'bulk'" [class.btn-secondary]="activeTab !== 'bulk'" (click)="activeTab = 'bulk'">Daily Sheet (Bulk)</button>
        <button class="btn" [class.btn-primary]="activeTab === 'single'" [class.btn-secondary]="activeTab !== 'single'" (click)="activeTab = 'single'">Single Entry</button>
      </div>
    </app-page-header>

    @if (activeTab === 'bulk') {
      <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <label class="form-label mb-0">Date:</label>
            <input type="date" [(ngModel)]="sheetDate" (change)="loadDailySheet()" class="form-input w-44" />
          </div>
          <button class="btn btn-primary" (click)="saveBulkSheet()">Save Attendance Sheet</button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="p-2 text-left">Code</th>
                <th class="p-2 text-left">Employee Name</th>
                <th class="p-2 text-left">Department</th>
                <th class="p-2 text-center w-36">Attendance Status</th>
                <th class="p-2 text-center w-28">Overtime (Hrs)</th>
              </tr>
            </thead>
            <tbody>
              @for (emp of employeeRows(); track emp.id) {
                <tr class="border-b last:border-0 hover:bg-gray-50">
                  <td class="p-2 font-mono">{{ emp.employee_code }}</td>
                  <td class="p-2 font-medium">{{ emp.name }}</td>
                  <td class="p-2 text-gray-500">{{ emp.department_name }}</td>
                  <td class="p-2 text-center">
                    <select [(ngModel)]="emp.status" class="form-input text-xs py-1">
                      <option value="present">Present</option>
                      <option value="absent">Absent</option>
                      <option value="half_day">Half Day</option>
                      <option value="leave">On Leave</option>
                    </select>
                  </td>
                  <td class="p-2 text-center">
                    <input type="number" [(ngModel)]="emp.overtime_hours" min="0" step="0.5" class="form-input text-xs py-1 text-center" />
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </div>
    } @else {
      <!-- Single Entry Form -->
      <div class="card max-w-lg mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Log Employee Attendance</h3>
        <form (ngSubmit)="saveSingle()">
          <div class="mb-4">
            <label class="form-label">Employee *</label>
            <select [(ngModel)]="singleForm.employee_id" name="employee_id" required class="form-input">
              <option [ngValue]="null">Select Employee</option>
              @for (e of employees(); track e.id) {
                <option [value]="e.id">{{ e.name }} ({{ e.employee_code }})</option>
              }
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="form-label">Date *</label>
              <input type="date" [(ngModel)]="singleForm.date" name="date" required class="form-input" />
            </div>
            <div>
              <label class="form-label">Status *</label>
              <select [(ngModel)]="singleForm.status" name="status" required class="form-input">
                <option value="present">Present</option>
                <option value="absent">Absent</option>
                <option value="half_day">Half Day</option>
                <option value="leave">Leave</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="form-label">In Time</label>
              <input type="time" [(ngModel)]="singleForm.in_time" name="in_time" class="form-input" />
            </div>
            <div>
              <label class="form-label">Out Time</label>
              <input type="time" [(ngModel)]="singleForm.out_time" name="out_time" class="form-input" />
            </div>
          </div>
          <div class="mb-6">
            <label class="form-label">Overtime Hours</label>
            <input type="number" [(ngModel)]="singleForm.overtime_hours" name="overtime_hours" min="0" step="0.5" class="form-input" />
          </div>
          <button type="submit" class="btn btn-primary w-full justify-center">Record Attendance</button>
        </form>
      </div>
    }
  `
})
export class AttendanceComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  activeTab: 'bulk' | 'single' = 'bulk';
  sheetDate = new Date().toISOString().substring(0, 10);

  employees = signal<any[]>([]);
  employeeRows = signal<any[]>([]);

  singleForm: any = {
    employee_id: null,
    date: new Date().toISOString().substring(0, 10),
    status: 'present',
    in_time: '09:00',
    out_time: '18:00',
    overtime_hours: 0
  };

  ngOnInit() {
    this.api.get<any>('/hr/employees').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.employees.set(list);
        this.loadDailySheet();
      }
    });
  }

  loadDailySheet() {
    this.api.get<any>('/hr/attendance', { date: this.sheetDate }).subscribe({
      next: (res) => {
        const existing = Array.isArray(res) ? res : (res?.data || []);
        const map = new Map<number, any>();
        existing.forEach((x: any) => map.set(x.employee_id, x));

        const rows = this.employees().map(emp => {
          const rec = map.get(emp.id);
          return {
            ...emp,
            status: rec?.status || 'present',
            overtime_hours: rec?.overtime_hours || 0
          };
        });
        this.employeeRows.set(rows);
      }
    });
  }

  saveBulkSheet() {
    const records = this.employeeRows().map(r => ({
      employee_id: r.id,
      date: this.sheetDate,
      status: r.status,
      overtime_hours: Number(r.overtime_hours) || 0
    }));

    this.api.post('/hr/attendance/bulk', { records }).subscribe({
      next: () => this.toast.success('Attendance saved for ' + this.sheetDate),
      error: () => this.toast.error('Failed to save attendance')
    });
  }

  saveSingle() {
    if (!this.singleForm.employee_id) {
      this.toast.error('Please select an employee');
      return;
    }
    this.api.post('/hr/attendance', this.singleForm).subscribe({
      next: () => this.toast.success('Attendance logged successfully'),
      error: () => this.toast.error('Failed to save attendance')
    });
  }
}
