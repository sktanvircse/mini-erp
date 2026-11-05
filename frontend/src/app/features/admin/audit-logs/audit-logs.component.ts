import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { DataTableComponent } from '../../../shared/components/data-table/data-table.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-audit-logs',
  standalone: true,
  imports: [CommonModule, FormsModule, DataTableComponent, PageHeaderComponent],
  template: `
    <app-page-header title="System Audit Logs" [breadcrumbs]="[{ label: 'Admin' }, { label: 'Audit Logs' }]" />

    <div class="card">
      <app-data-table
        [columns]="columns"
        [rows]="logs()"
        [total]="total()"
        [page]="page()"
        [perPage]="perPage"
        [loading]="loading()"
        (pageChange)="onPageChange($event)"
        (searchChange)="onSearchChange($event)"
      />
    </div>
  `
})
export class AuditLogsComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  logs = signal<any[]>([]);
  total = signal(0);
  page = signal(1);
  perPage = 20;
  loading = signal(false);
  searchQuery = '';

  columns = [
    { key: 'created_at', label: 'Timestamp', sortable: true },
    { key: 'user_name', label: 'User' },
    { key: 'action', label: 'Action', sortable: true },
    { key: 'entity_type', label: 'Entity' },
    { key: 'entity_id', label: 'Entity ID' },
    { key: 'ip_address', label: 'IP' }
  ];

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading.set(true);
    this.api.get<any>('/admin/audit-logs', { page: this.page(), per_page: this.perPage, search: this.searchQuery }).subscribe({
      next: (res) => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.logs.set(data);
        this.total.set(res?.total ?? data.length);
        this.loading.set(false);
      },
      error: () => {
        this.toast.error('Failed to load audit logs');
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
}
