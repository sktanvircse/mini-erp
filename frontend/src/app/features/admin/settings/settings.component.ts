import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="System Configuration" [breadcrumbs]="[{ label: 'Admin' }, { label: 'Settings' }]">
      <button class="btn btn-primary" [disabled]="saving()" (click)="save()">
        {{ saving() ? 'Saving...' : 'Save All Settings' }}
      </button>
    </app-page-header>

    <div class="card max-w-2xl">
      <div class="space-y-4">
        @for (item of settingsList(); track item.key) {
          <div class="border-b pb-3 last:border-0">
            <label class="form-label font-mono text-xs text-blue-700">{{ item.key }}</label>
            <input type="text" [(ngModel)]="item.value" class="form-input" />
          </div>
        }
      </div>
    </div>
  `
})
export class SettingsComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  settingsList = signal<{ key: string; value: string }[]>([]);
  saving = signal(false);

  ngOnInit() {
    this.api.get<any>('/admin/settings').subscribe({
      next: (res) => {
        const raw = res?.data || res || {};
        if (Array.isArray(raw)) {
          this.settingsList.set(raw);
        } else {
          const list = Object.entries(raw).map(([key, value]) => ({ key, value: String(value) }));
          this.settingsList.set(list);
        }
      }
    });
  }

  save() {
    this.saving.set(true);
    const obj: Record<string, string> = {};
    this.settingsList().forEach(item => {
      obj[item.key] = item.value;
    });

    this.api.put('/admin/settings', obj).subscribe({
      next: () => {
        this.toast.success('Settings updated successfully!');
        this.saving.set(false);
      },
      error: () => {
        this.toast.error('Failed to update settings');
        this.saving.set(false);
      }
    });
  }
}
