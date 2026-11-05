import { Component, Input, Output, EventEmitter, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-data-table',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div>
      <!-- Search + header actions -->
      <div class="flex items-center justify-between mb-4 gap-3">
        <div class="flex-1 max-w-xs">
          <input [(ngModel)]="searchValue" (ngModelChange)="onSearch($event)"
            placeholder="Search…" class="form-input" />
        </div>
        <div class="flex gap-2">
          <ng-content select="[actions]"></ng-content>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="table-auto w-full divide-y divide-gray-200">
          <thead>
            <tr>
              @for (col of columns; track col.key) {
                <th [class]="col.thClass ?? ''" (click)="col.sortable && sort(col.key)" [class.cursor-pointer]="col.sortable">
                  {{ col.label }}
                  @if (col.sortable) {
                    <span class="ml-1 text-gray-400">{{ sortIcon(col.key) }}</span>
                  }
                </th>
              }
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            @if (loading) {
              <tr><td [attr.colspan]="columns.length" class="text-center py-12 text-gray-400">Loading…</td></tr>
            } @else if (rows.length === 0) {
              <tr><td [attr.colspan]="columns.length" class="text-center py-12 text-gray-400">No records found.</td></tr>
            } @else {
              @for (row of rows; track row[idKey] ?? $index) {
                <tr class="hover:bg-blue-50 cursor-pointer" (click)="rowClick.emit(row)">
                  @for (col of columns; track col.key) {
                    <td [class]="col.tdClass ?? ''">
                      <ng-container *ngTemplateOutlet="col.template ?? defaultCell; context: { $implicit: row, col: col }"></ng-container>
                      <ng-template #defaultCell>{{ row[col.key] ?? '-' }}</ng-template>
                    </td>
                  }
                </tr>
              }
            }
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      @if (total > 0) {
        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
          <span>Showing {{ (page - 1) * perPage + 1 }}–{{ min(page * perPage, total) }} of {{ total }}</span>
          <div class="flex gap-1">
            <button [disabled]="page === 1" (click)="goPage(page - 1)" class="btn btn-secondary btn-sm">‹ Prev</button>
            @for (p of pageNumbers(); track p) {
              <button [class.btn-primary]="p === page" [class.btn-secondary]="p !== page"
                (click)="goPage(p)" class="btn btn-sm">{{ p }}</button>
            }
            <button [disabled]="page === lastPage" (click)="goPage(page + 1)" class="btn btn-secondary btn-sm">Next ›</button>
          </div>
        </div>
      }
    </div>
  `
})
export class DataTableComponent implements OnChanges {
  @Input() columns: any[] = [];
  @Input() rows: any[] = [];
  @Input() total = 0;
  @Input() page = 1;
  @Input() perPage = 15;
  @Input() loading = false;
  @Input() idKey = 'id';

  @Output() pageChange   = new EventEmitter<number>();
  @Output() searchChange = new EventEmitter<string>();
  @Output() sortChange   = new EventEmitter<{key: string; dir: 'asc'|'desc'}>();
  @Output() rowClick     = new EventEmitter<any>();

  searchValue = '';
  sortKey = '';
  sortDir: 'asc'|'desc' = 'asc';

  get lastPage() { return Math.ceil(this.total / this.perPage) || 1; }

  ngOnChanges(changes: SimpleChanges) {}

  onSearch(val: string) { this.searchChange.emit(val); }

  sort(key: string) {
    if (this.sortKey === key) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
    else { this.sortKey = key; this.sortDir = 'asc'; }
    this.sortChange.emit({ key: this.sortKey, dir: this.sortDir });
  }

  sortIcon(key: string) {
    if (this.sortKey !== key) return '↕';
    return this.sortDir === 'asc' ? '↑' : '↓';
  }

  goPage(p: number) { this.pageChange.emit(p); }

  pageNumbers(): number[] {
    const pages = [];
    const start = Math.max(1, this.page - 2);
    const end   = Math.min(this.lastPage, this.page + 2);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
  }

  min(a: number, b: number) { return Math.min(a, b); }
}
