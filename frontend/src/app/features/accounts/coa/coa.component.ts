import {
  Component, inject, signal, computed, OnInit
} from '@angular/core';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { DecimalPipe, TitleCasePipe } from '@angular/common';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

interface Account {
  id: number;
  code: string;
  name: string;
  account_type: 'asset' | 'liability' | 'equity' | 'income' | 'expense';
  parent_id: number | null;
  level: number;
  balance: number;
  normal_balance: 'debit' | 'credit';
  is_active: boolean;
  children?: Account[];
}

@Component({
  selector: 'app-coa',
  standalone: true,
  imports: [ReactiveFormsModule, DecimalPipe, TitleCasePipe, PageHeaderComponent, StatusBadgeComponent],
  template: `
    <app-page-header title="Chart of Accounts" subtitle="Manage your accounting structure">
      <button class="btn btn-primary" (click)="openModal()">+ New Account</button>
    </app-page-header>

    <div class="card">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50">
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Code</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Account Name</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Type</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Normal Balance</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Balance</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Actions</th>
            </tr>
          </thead>
          <tbody>
            @if (loading()) {
              <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td>
              </tr>
            } @else if (flatTree().length === 0) {
              <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No accounts found.</td>
              </tr>
            } @else {
              @for (account of flatTree(); track account.id) {
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                  <td class="px-4 py-3 font-mono text-gray-700">{{ account.code }}</td>
                  <td class="px-4 py-3">
                    <span [style.padding-left.rem]="account.level * 1.5">
                      @if (account.level > 0) {
                        <span class="text-gray-400 mr-1">└─</span>
                      }
                      <span [class]="account.level === 0 ? 'font-semibold text-gray-800' : 'text-gray-700'">
                        {{ account.name }}
                      </span>
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span [class]="typeBadgeClass(account.account_type)">
                      {{ account.account_type | titlecase }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <span [class]="account.normal_balance === 'debit'
                      ? 'text-blue-700 font-medium' : 'text-purple-700 font-medium'">
                      {{ account.normal_balance | titlecase }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right font-mono">
                    {{ account.balance | number:'1.2-2' }}
                  </td>
                  <td class="px-4 py-3">
                    <app-status-badge [status]="account.is_active ? 'active' : 'inactive'" />
                  </td>
                  <td class="px-4 py-3">
                    <button class="btn btn-secondary text-xs" (click)="openModal(account)">Edit</button>
                  </td>
                </tr>
              }
            }
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
    @if (showModal()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4">
          <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
              {{ editingAccount() ? 'Edit Account' : 'New Account' }}
            </h2>
            <button (click)="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
          </div>
          <form [formGroup]="accountForm" (ngSubmit)="saveAccount()" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Account Code *</label>
                <input formControlName="code" class="form-input" placeholder="e.g. 1001" />
                @if (accountForm.get('code')?.invalid && accountForm.get('code')?.touched) {
                  <p class="text-red-500 text-xs mt-1">Code is required</p>
                }
              </div>
              <div>
                <label class="form-label">Account Name *</label>
                <input formControlName="name" class="form-input" placeholder="Account name" />
                @if (accountForm.get('name')?.invalid && accountForm.get('name')?.touched) {
                  <p class="text-red-500 text-xs mt-1">Name is required</p>
                }
              </div>
            </div>
            <div>
              <label class="form-label">Account Type *</label>
              <select formControlName="account_type" class="form-input">
                <option value="">Select type</option>
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
              </select>
              @if (accountForm.get('account_type')?.invalid && accountForm.get('account_type')?.touched) {
                <p class="text-red-500 text-xs mt-1">Type is required</p>
              }
            </div>
            <div>
              <label class="form-label">Parent Account</label>
              <select formControlName="parent_id" class="form-input">
                <option [value]="null">None (Root Account)</option>
                @for (acc of accounts(); track acc.id) {
                  <option [value]="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                }
              </select>
            </div>
            <div class="flex items-center gap-3">
              <input type="checkbox" formControlName="is_active" id="is_active"
                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <label for="is_active" class="form-label mb-0">Active</label>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
              <button type="button" class="btn btn-secondary" (click)="closeModal()">Cancel</button>
              <button type="submit" class="btn btn-primary" [disabled]="accountForm.invalid || saving()">
                {{ saving() ? 'Saving...' : (editingAccount() ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class CoaComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);
  private fb = inject(FormBuilder);

  accounts = signal<Account[]>([]);
  loading = signal(false);
  saving = signal(false);
  showModal = signal(false);
  editingAccount = signal<Account | null>(null);

  accountForm: FormGroup = this.fb.group({
    code: ['', Validators.required],
    name: ['', Validators.required],
    account_type: ['', Validators.required],
    parent_id: [null],
    is_active: [true]
  });

  flatTree = computed(() => {
    const list = this.accounts();
    const result: (Account & { level: number })[] = [];

    const buildTree = (parentId: number | null, level: number) => {
      list
        .filter(a => a.parent_id === parentId)
        .sort((a, b) => a.code.localeCompare(b.code))
        .forEach(a => {
          result.push({ ...a, level });
          buildTree(a.id, level + 1);
        });
    };
    buildTree(null, 0);
    return result;
  });

  ngOnInit() { this.loadAccounts(); }

  loadAccounts() {
    this.loading.set(true);
    this.api.get<any>('/accounts').subscribe({
      next: res => {
        const data = Array.isArray(res) ? res : (res?.data || []);
        this.accounts.set(data);
        this.loading.set(false);
      },
      error: () => { this.toast.error('Failed to load accounts'); this.loading.set(false); }
    });
  }

  typeBadgeClass(type: string): string {
    const map: Record<string, string> = {
      asset: 'badge-blue',
      liability: 'badge-red',
      equity: 'badge-purple',
      income: 'badge-green',
      expense: 'badge-orange'
    };
    return map[type] ?? 'badge-gray';
  }

  openModal(account?: Account) {
    this.editingAccount.set(account ?? null);
    if (account) {
      this.accountForm.patchValue({
        code: account.code,
        name: account.name,
        account_type: account.account_type,
        parent_id: account.parent_id,
        is_active: account.is_active
      });
    } else {
      this.accountForm.reset({ is_active: true, parent_id: null });
    }
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.editingAccount.set(null);
    this.accountForm.reset({ is_active: true });
  }

  saveAccount() {
    if (this.accountForm.invalid) { this.accountForm.markAllAsTouched(); return; }
    this.saving.set(true);
    const payload = this.accountForm.value;
    const editing = this.editingAccount();

    const req = editing
      ? this.api.put<Account>(`/accounts/${editing.id}`, payload)
      : this.api.post<Account>('/accounts', payload);

    req.subscribe({
      next: () => {
        this.toast.success(editing ? 'Account updated' : 'Account created');
        this.closeModal();
        this.loadAccounts();
        this.saving.set(false);
      },
      error: () => {
        this.toast.error('Failed to save account');
        this.saving.set(false);
      }
    });
  }
}
