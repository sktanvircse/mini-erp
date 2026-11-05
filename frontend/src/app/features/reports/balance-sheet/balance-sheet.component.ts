import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-balance-sheet',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Balance Sheet" [breadcrumbs]="[{ label: 'Reports' }, { label: 'Balance Sheet' }]">
      <button class="btn btn-secondary no-print" (click)="print()">🖨️ Print</button>
    </app-page-header>

    <div class="card mb-6 no-print">
      <div class="flex items-end gap-4">
        <div>
          <label class="form-label">As of Date</label>
          <input type="date" [(ngModel)]="asOfDate" class="form-input" />
        </div>
        <button class="btn btn-primary" (click)="loadReport()">Generate Balance Sheet</button>
      </div>
    </div>

    <div class="card max-w-5xl mx-auto">
      <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-gray-900">Statement of Financial Position (Balance Sheet)</h2>
        <p class="text-xs text-gray-500">As of: {{ asOfDate }}</p>
        @if (isBalanced()) {
          <span class="inline-block mt-2 px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold">
            ✓ Balance Sheet is Balanced (Assets = Liabilities + Equity)
          </span>
        } @else {
          <span class="inline-block mt-2 px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold">
            Difference: \${{ difference() | number:'1.2-2' }}
          </span>
        }
      </div>

      <div class="grid grid-cols-2 gap-8">
        <!-- Assets Column -->
        <div>
          <h3 class="font-bold text-sm text-gray-900 uppercase border-b pb-1 mb-2 flex justify-between">
            <span>Assets</span>
            <span class="text-blue-800">\${{ totalAssets() | number:'1.2-2' }}</span>
          </h3>
          <table class="w-full text-xs">
            <tbody>
              @if (!assets().length) {
                <tr><td colspan="2" class="p-3 text-center text-gray-400">No asset accounts recorded</td></tr>
              }
              @for (item of assets(); track (item.account_name || item.name)) {
                <tr class="border-b last:border-0 hover:bg-gray-50">
                  <td class="p-2.5 font-medium text-gray-800">[{{ item.account_code || item.code }}] {{ item.account_name || item.name }}</td>
                  <td class="p-2.5 text-right font-semibold text-gray-900">\${{ (item.amount || item.balance || 0) | number:'1.2-2' }}</td>
                </tr>
              }
            </tbody>
            <tfoot>
              <tr class="font-bold bg-blue-50">
                <td class="p-2.5 text-blue-900">TOTAL ASSETS:</td>
                <td class="p-2.5 text-right text-blue-900 text-sm">\${{ totalAssets() | number:'1.2-2' }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Liabilities & Equity Column -->
        <div>
          <!-- Liabilities Section -->
          <div class="mb-6">
            <h3 class="font-bold text-sm text-gray-900 uppercase border-b pb-1 mb-2 flex justify-between">
              <span>Liabilities</span>
              <span class="text-rose-800">\${{ totalLiabilities() | number:'1.2-2' }}</span>
            </h3>
            <table class="w-full text-xs mb-2">
              <tbody>
                @if (!liabilities().length) {
                  <tr><td colspan="2" class="p-3 text-center text-gray-400">No liability accounts recorded</td></tr>
                }
                @for (item of liabilities(); track (item.account_name || item.name)) {
                  <tr class="border-b last:border-0 hover:bg-gray-50">
                    <td class="p-2.5 font-medium text-gray-800">[{{ item.account_code || item.code }}] {{ item.account_name || item.name }}</td>
                    <td class="p-2.5 text-right font-semibold text-gray-900">\${{ (item.amount || item.balance || 0) | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
              <tfoot>
                <tr class="font-bold bg-rose-50 text-rose-900">
                  <td class="p-2.5">Total Liabilities:</td>
                  <td class="p-2.5 text-right">\${{ totalLiabilities() | number:'1.2-2' }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Equity Section -->
          <div class="mb-4">
            <h3 class="font-bold text-sm text-gray-900 uppercase border-b pb-1 mb-2 flex justify-between">
              <span>Equity & Retained Earnings</span>
              <span class="text-purple-800">\${{ totalEquity() | number:'1.2-2' }}</span>
            </h3>
            <table class="w-full text-xs mb-2">
              <tbody>
                @if (!equity().length) {
                  <tr><td colspan="2" class="p-3 text-center text-gray-400">No equity accounts recorded</td></tr>
                }
                @for (item of equity(); track (item.account_name || item.name)) {
                  <tr class="border-b last:border-0 hover:bg-gray-50">
                    <td class="p-2.5 font-medium text-gray-800">[{{ item.account_code || item.code }}] {{ item.account_name || item.name }}</td>
                    <td class="p-2.5 text-right font-semibold text-gray-900">\${{ (item.amount || item.balance || 0) | number:'1.2-2' }}</td>
                  </tr>
                }
                @if (retainedCurrentPeriod() !== 0) {
                  <tr class="border-b last:border-0 hover:bg-gray-50 bg-purple-50/50">
                    <td class="p-2.5 font-medium text-purple-900 italic">Net Income for Current Period</td>
                    <td class="p-2.5 text-right font-semibold text-purple-900">\${{ retainedCurrentPeriod() | number:'1.2-2' }}</td>
                  </tr>
                }
              </tbody>
              <tfoot>
                <tr class="font-bold bg-purple-50 text-purple-900">
                  <td class="p-2.5">Total Equity:</td>
                  <td class="p-2.5 text-right">\${{ totalEquity() | number:'1.2-2' }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Total Liabilities & Equity Check -->
          <div class="p-3 font-bold bg-gradient-to-r from-purple-100 to-indigo-100 rounded-lg flex justify-between items-center text-xs text-purple-950 border border-purple-200">
            <span class="uppercase">TOTAL LIABILITIES & EQUITY:</span>
            <span class="text-sm font-extrabold">\${{ totalLiabilitiesAndEquity() | number:'1.2-2' }}</span>
          </div>
        </div>
      </div>
    </div>
  `
})
export class BalanceSheetComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  asOfDate = new Date().toISOString().substring(0, 10);

  assets = signal<any[]>([]);
  liabilities = signal<any[]>([]);
  equity = signal<any[]>([]);
  totalAssets = signal(0);
  totalLiabilities = signal(0);
  totalEquity = signal(0);
  retainedCurrentPeriod = signal(0);
  totalLiabilitiesAndEquity = signal(0);
  isBalanced = signal(true);
  difference = signal(0);

  ngOnInit() {
    this.loadReport();
  }

  loadReport() {
    this.api.get<any>('/reports/balance-sheet', { as_of_date: this.asOfDate, to_date: this.asOfDate }).subscribe({
      next: (res) => {
        const data = res?.data ?? res;
        const ass = data?.assets || [];
        const lia = data?.liabilities || [];
        const equ = data?.equity || [];
        this.assets.set(ass);
        this.liabilities.set(lia);
        this.equity.set(equ);

        const aTot = data?.total_assets != null ? Number(data.total_assets) : ass.reduce((a: number, c: any) => a + Number(c.amount || c.balance || 0), 0);
        const lTot = data?.total_liabilities != null ? Number(data.total_liabilities) : lia.reduce((a: number, c: any) => a + Number(c.amount || c.balance || 0), 0);
        const eTot = data?.total_equity != null ? Number(data.total_equity) : equ.reduce((a: number, c: any) => a + Number(c.amount || c.balance || 0), 0);
        const retCur = Number(data?.retained_earnings_current) || 0;
        const combTot = data?.total_liabilities_and_equity != null ? Number(data.total_liabilities_and_equity) : (lTot + eTot);

        this.totalAssets.set(aTot);
        this.totalLiabilities.set(lTot);
        this.totalEquity.set(eTot);
        this.retainedCurrentPeriod.set(retCur);
        this.totalLiabilitiesAndEquity.set(combTot);
        this.isBalanced.set(data?.is_balanced ?? (Math.abs(aTot - combTot) < 0.01));
        this.difference.set(data?.difference ?? Math.abs(aTot - combTot));
      },
      error: () => this.toast.error('Failed to load balance sheet')
    });
  }

  print() {
    window.print();
  }
}
