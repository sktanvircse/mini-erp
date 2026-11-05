import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-departments',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent],
  template: `
    <app-page-header title="Departments & Designations" [breadcrumbs]="[{ label: 'HR' }, { label: 'Departments' }]">
      <div class="flex gap-2">
        <button class="btn btn-secondary" (click)="openDesignationModal()">+ New Designation</button>
        <button class="btn btn-primary" (click)="openDeptModal(null)">+ New Department</button>
      </div>
    </app-page-header>

    <div class="grid grid-cols-2 gap-6">
      <!-- Departments Table -->
      <div class="card">
        <h3 class="text-base font-semibold text-gray-900 mb-3">Departments</h3>
        <table class="w-full text-xs">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="p-2 text-left">Name</th>
              <th class="p-2 text-left">Description</th>
              <th class="p-2 w-16"></th>
            </tr>
          </thead>
          <tbody>
            @for (d of departments(); track d.id) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2 font-medium">{{ d.name }}</td>
                <td class="p-2 text-gray-500">{{ d.description || '-' }}</td>
                <td class="p-2 text-right">
                  <button class="text-blue-600 hover:underline" (click)="openDeptModal(d)">Edit</button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      <!-- Designations Table -->
      <div class="card">
        <h3 class="text-base font-semibold text-gray-900 mb-3">Designations</h3>
        <table class="w-full text-xs">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="p-2 text-left">Designation</th>
              <th class="p-2 text-left">Department</th>
            </tr>
          </thead>
          <tbody>
            @for (ds of designations(); track ds.id) {
              <tr class="border-b last:border-0 hover:bg-gray-50">
                <td class="p-2 font-medium">{{ ds.name }}</td>
                <td class="p-2 text-gray-500">{{ ds.department_name || '-' }}</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    </div>

    <!-- Dept Modal -->
    @if (deptModalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ currentDept ? 'Edit Department' : 'New Department' }}</h3>
          <form (ngSubmit)="saveDept()">
            <div class="mb-4">
              <label class="form-label">Department Name *</label>
              <input type="text" [(ngModel)]="deptForm.name" name="name" required class="form-input" />
            </div>
            <div class="mb-6">
              <label class="form-label">Description</label>
              <textarea [(ngModel)]="deptForm.description" name="description" rows="2" class="form-input"></textarea>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="deptModalOpen.set(false)">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Designation Modal -->
    @if (desigModalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">New Designation</h3>
          <form (ngSubmit)="saveDesignation()">
            <div class="mb-4">
              <label class="form-label">Department</label>
              <select [(ngModel)]="desigForm.department_id" name="department_id" class="form-input">
                <option [ngValue]="null">Select Department</option>
                @for (d of departments(); track d.id) {
                  <option [value]="d.id">{{ d.name }}</option>
                }
              </select>
            </div>
            <div class="mb-6">
              <label class="form-label">Designation Title *</label>
              <input type="text" [(ngModel)]="desigForm.name" name="name" required class="form-input" placeholder="e.g. Senior Accountant" />
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="desigModalOpen.set(false)">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Designation</button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class DepartmentsComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  departments = signal<any[]>([]);
  designations = signal<any[]>([]);

  deptModalOpen = signal(false);
  desigModalOpen = signal(false);

  currentDept: any = null;
  deptForm = { name: '', description: '' };
  desigForm: any = { department_id: null, name: '' };

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.api.get<any>('/hr/departments').subscribe({
      next: (res) => this.departments.set(Array.isArray(res) ? res : (res?.data || []))
    });
    this.api.get<any>('/hr/designations').subscribe({
      next: (res) => this.designations.set(Array.isArray(res) ? res : (res?.data || []))
    });
  }

  openDeptModal(d: any) {
    this.currentDept = d;
    this.deptForm = d ? { name: d.name, description: d.description || '' } : { name: '', description: '' };
    this.deptModalOpen.set(true);
  }

  saveDept() {
    if (!this.deptForm.name.trim()) return;
    if (this.currentDept) {
      this.api.put(`/hr/departments/${this.currentDept.id}`, this.deptForm).subscribe({
        next: () => {
          this.toast.success('Department updated');
          this.deptModalOpen.set(false);
          this.loadData();
        }
      });
    } else {
      this.api.post('/hr/departments', this.deptForm).subscribe({
        next: () => {
          this.toast.success('Department created');
          this.deptModalOpen.set(false);
          this.loadData();
        }
      });
    }
  }

  openDesignationModal() {
    this.desigForm = { department_id: this.departments()[0]?.id || null, name: '' };
    this.desigModalOpen.set(true);
  }

  saveDesignation() {
    if (!this.desigForm.name.trim()) return;
    this.api.post('/hr/designations', this.desigForm).subscribe({
      next: () => {
        this.toast.success('Designation added');
        this.desigModalOpen.set(false);
        this.loadData();
      }
    });
  }
}
