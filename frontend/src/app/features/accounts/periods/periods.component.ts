import {
  Component, inject, signal, OnInit
} from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';
import { ConfirmDialogComponent } from '../../../shared/components/confirm-dialog/confirm-dialog.component';

interface Period {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  status: 'open' | 'closed';
}

@Component({
  selector: 'app-periods',
  standalone: true,
  imports: [ReactiveFormsModule, DatePipe, PageHeaderComponent, StatusBadgeComponent, ConfirmDialogComponent],
  template: `
    <app-page-header title="Financial Periods" subtitle="Manage accounting periods">
      <button class="btn btn-primary" (click)="openModal()">+ New Period</button>
    </app-page-header>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Period Name</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Start Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">End Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Actions</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Loading...</td>
              </tr>
            } @else if (periods().length === 0) {
              <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No periods found.</td>
              </tr>
            } @else {
              @for (period of periods(); track period.id) {
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 font-medium text-gray-800">{{ period.name }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ period.start_date | date:'mediumDate' }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ period.end_date | date:'mediumDate' }}</td>
                  <td class="px-4 py-3">
                    <app-status-badge [status]="period.status" />
                  </td>
                  <td class="px-4 py-3">
                    @if (period.status === 'open') {
                      <button
                        class="btn btn-danger text-xs"
                        (click)="requestToggle(period)"
                        [disabled]="togglingId() === period.id">
                        {{ togglingId() === period.id ? 'Closing...' : 'Close Period' }}
                      </button>
                    } @else {
                      <button
                        class="btn btn-secondary text-xs"
                        (click)="requestToggle(period)"
                        [disabled]="togglingId() === period.id">
                        {{ togglingId() === period.id ? 'Opening...' : 'Re-open' }}
                      </button>
                    }
                  </td>
                </tr>
              }
            }
          </tbody>
        </table>
      </div>
    </div>

    <!-- New Period Modal -->
    @if (showModal()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
          <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">New Financial Period</h2>
            <button (click)="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <form [formGroup]="periodForm" (ngSubmit)="savePeriod()" class="p-6 space-y-4">
            <div>
              <label class="form-label">Period Name *</label>
              <input formControlName="name" class="form-input" placeholder="e.g. Q1 FY2025" />
              @if (periodForm.get('name')?.invalid && periodForm.get('name')?.touched) {
                <p class="text-red-500 text-xs mt-1">Name is required</p>
              }
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Start Date *</label>
                <input type="date" formControlName="start_date" class="form-input" />
                @if (periodForm.get('start_date')?.invalid && periodForm.get('start_date')?.touched) {
                  <p class="text-red-500 text-xs mt-1">Start date is required</p>
                }
              </div>
              <div>
                <label class="form-label">End Date *</label>
                <input type="date" formControlName="end_date" class="form-input" />
                @if (periodForm.get('end_date')?.invalid && periodForm.get('end_date')?.touched) {
                  <p class="text-red-500 text-xs mt-1">End date is required</p>
                }
              </div>
            </div>
            @if (dateError()) {
              <p class="text-red-500 text-sm bg-red-50 border border-red-200 rounded p-2">
                {{ dateError() }}
              </p>
            }
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
              <button type="button" class="btn btn-secondary" (click)="closeModal()">Cancel</button>
              <button type="submit" class="btn btn-primary" [disabled]="periodForm.invalid || saving()">
                {{ saving() ? 'Creating...' : 'Create Period' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }

    <!-- Confirm Dialog -->
    @if (confirmPeriod()) {
      <app-confirm-dialog
        [title]="confirmPeriod()!.status === 'open' ? 'Close Period' : 'Re-open Period'"
        [message]="'Are you sure you want to ' + (confirmPeriod()!.status === 'open' ? 'close' : 're-open') + ' period &quot;' + confirmPeriod()!.name + '&quot;?'"
        (confirmed)="togglePeriod()"
        (cancelled)="confirmPeriod.set(null)" />
    }
  `
})
export class PeriodsComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);
  private fb = inject(FormBuilder);

  periods = signal<Period[]>([]);
  loading = signal(false);
  saving = signal(false);
  togglingId = signal<number | null>(null);
  showModal = signal(false);
  confirmPeriod = signal<Period | null>(null);
  dateError = signal<string | null>(null);

  periodForm: FormGroup = this.fb.group({
    name: ['', Validators.required],
    start_date: ['', Validators.required],
    end_date: ['', Validators.required]
  });

  ngOnInit() { this.loadPeriods(); }

  loadPeriods() {
    this.loading.set(true);
    this.api.get<any>('/accounts/periods').subscribe({
      next: res => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.periods.set(data);
        this.loading.set(false);
      },
      error: () => { this.toast.error('Failed to load periods'); this.loading.set(false); }
    });
  }

  openModal() { this.periodForm.reset(); this.showModal.set(true); this.dateError.set(null); }
  closeModal() { this.showModal.set(false); }

  savePeriod() {
    if (this.periodForm.invalid) { this.periodForm.markAllAsTouched(); return; }
    const { start_date, end_date } = this.periodForm.value;
    if (new Date(end_date) <= new Date(start_date)) {
      this.dateError.set('End date must be after start date.');
      return;
    }
    this.dateError.set(null);
    this.saving.set(true);
    this.api.post<Period>('/accounts/periods', this.periodForm.value).subscribe({
      next: () => {
        this.toast.success('Period created');
        this.closeModal();
        this.loadPeriods();
        this.saving.set(false);
      },
      error: () => { this.toast.error('Failed to create period'); this.saving.set(false); }
    });
  }

  requestToggle(period: Period) { this.confirmPeriod.set(period); }

  togglePeriod() {
    const period = this.confirmPeriod();
    if (!period) return;
    this.confirmPeriod.set(null);
    this.togglingId.set(period.id);
    const action = period.status === 'open' ? 'close' : 'open';
    this.api.patch(`/accounts/periods/${period.id}/${action}`, {}).subscribe({
      next: () => {
        this.toast.success(`Period ${action}d`);
        this.togglingId.set(null);
        this.loadPeriods();
      },
      error: () => {
        this.toast.error(`Failed to ${action} period`);
        this.togglingId.set(null);
      }
    });
  }
}
