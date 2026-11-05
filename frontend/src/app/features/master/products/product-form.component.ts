import {
  Component,
  OnInit,
  OnChanges,
  SimpleChanges,
  inject,
  signal,
  input,
  output,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { Product } from './products-list.component';

interface Category {
  id: number;
  name: string;
}

interface Unit {
  id: number;
  name: string;
  symbol: string;
}

@Component({
  selector: 'app-product-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    @if (open()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-xl font-semibold text-gray-800">
              {{ product() ? 'Edit Product' : 'New Product' }}
            </h2>
            <button class="text-gray-400 hover:text-gray-600 text-2xl leading-none" (click)="close()">&times;</button>
          </div>

          <!-- Body -->
          <form [formGroup]="form" (ngSubmit)="submit()" class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Code -->
              <div>
                <label class="form-label">Code <span class="text-red-500">*</span></label>
                <input formControlName="code" type="text" class="form-input" placeholder="e.g. PRD-001" />
                @if (f['code'].invalid && f['code'].touched) {
                  <p class="text-red-500 text-xs mt-1">Code is required.</p>
                }
              </div>

              <!-- Name -->
              <div>
                <label class="form-label">Name <span class="text-red-500">*</span></label>
                <input formControlName="name" type="text" class="form-input" placeholder="Product name" />
                @if (f['name'].invalid && f['name'].touched) {
                  <p class="text-red-500 text-xs mt-1">Name is required.</p>
                }
              </div>

              <!-- Category -->
              <div>
                <label class="form-label">Category <span class="text-red-500">*</span></label>
                <select formControlName="category_id" class="form-input">
                  <option value="">— Select Category —</option>
                  @for (cat of categories(); track cat.id) {
                    <option [value]="cat.id">{{ cat.name }}</option>
                  }
                </select>
                @if (f['category_id'].invalid && f['category_id'].touched) {
                  <p class="text-red-500 text-xs mt-1">Category is required.</p>
                }
              </div>

              <!-- Unit -->
              <div>
                <label class="form-label">Unit <span class="text-red-500">*</span></label>
                <select formControlName="unit_id" class="form-input">
                  <option value="">— Select Unit —</option>
                  @for (unit of units(); track unit.id) {
                    <option [value]="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                  }
                </select>
                @if (f['unit_id'].invalid && f['unit_id'].touched) {
                  <p class="text-red-500 text-xs mt-1">Unit is required.</p>
                }
              </div>

              <!-- Purchase Price -->
              <div>
                <label class="form-label">Purchase Price <span class="text-red-500">*</span></label>
                <input formControlName="purchase_price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
                @if (f['purchase_price'].invalid && f['purchase_price'].touched) {
                  <p class="text-red-500 text-xs mt-1">Valid purchase price required.</p>
                }
              </div>

              <!-- Sale Price -->
              <div>
                <label class="form-label">Sale Price <span class="text-red-500">*</span></label>
                <input formControlName="sale_price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
                @if (f['sale_price'].invalid && f['sale_price'].touched) {
                  <p class="text-red-500 text-xs mt-1">Valid sale price required.</p>
                }
              </div>

              <!-- Reorder Level -->
              <div>
                <label class="form-label">Reorder Level</label>
                <input formControlName="reorder_level" type="number" min="0" class="form-input" placeholder="0" />
              </div>

              <!-- Active -->
              <div class="flex items-center gap-3 mt-6">
                <input formControlName="is_active" type="checkbox" id="is_active" class="w-4 h-4 accent-blue-600" />
                <label for="is_active" class="form-label mb-0 cursor-pointer">Active</label>
              </div>
            </div>

            <!-- Description -->
            <div>
              <label class="form-label">Description</label>
              <textarea formControlName="description" class="form-input" rows="3" placeholder="Optional description..."></textarea>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
              <button type="button" class="btn btn-secondary" (click)="close()">Cancel</button>
              <button type="submit" class="btn btn-primary" [disabled]="saving()">
                @if (saving()) {
                  <span>Saving…</span>
                } @else {
                  <span>{{ product() ? 'Update' : 'Create' }} Product</span>
                }
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `,
})
export class ProductFormComponent implements OnInit, OnChanges {
  open = input<boolean>(false);
  product = input<Product | null>(null);
  saved = output<void>();
  closed = output<void>();

  private api = inject(ApiService);
  private toast = inject(ToastService);
  private fb = inject(FormBuilder);

  categories = signal<Category[]>([]);
  units = signal<Unit[]>([]);
  saving = signal(false);

  form: FormGroup = this.fb.group({
    code: ['', Validators.required],
    name: ['', Validators.required],
    category_id: ['', Validators.required],
    unit_id: ['', Validators.required],
    purchase_price: [0, [Validators.required, Validators.min(0)]],
    sale_price: [0, [Validators.required, Validators.min(0)]],
    reorder_level: [0],
    description: [''],
    is_active: [true],
  });

  get f() {
    return this.form.controls;
  }

  ngOnInit(): void {
    this.loadCategories();
    this.loadUnits();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['open'] && this.open()) {
      this.resetForm();
    }
    if (changes['product'] && this.product()) {
      this.patchForm(this.product()!);
    }
  }

  loadCategories(): void {
    this.api.getList<Category>('/categories', { per_page: 1000 }).subscribe({
      next: (res: any) => this.categories.set(res.data ?? res.results ?? res ?? []),
      error: () => this.toast.error('Failed to load categories'),
    });
  }

  loadUnits(): void {
    this.api.getList<Unit>('/units', { per_page: 1000 }).subscribe({
      next: (res: any) => this.units.set(res.data ?? res.results ?? res ?? []),
      error: () => this.toast.error('Failed to load units'),
    });
  }

  resetForm(): void {
    const p = this.product();
    if (p) {
      this.patchForm(p);
    } else {
      this.form.reset({
        code: '',
        name: '',
        category_id: '',
        unit_id: '',
        purchase_price: 0,
        sale_price: 0,
        reorder_level: 0,
        description: '',
        is_active: true,
      });
    }
  }

  patchForm(p: Product): void {
    this.form.patchValue({
      code: p.code,
      name: p.name,
      category_id: p.category_id,
      unit_id: p.unit_id,
      purchase_price: p.purchase_price,
      sale_price: p.sale_price,
      reorder_level: p.reorder_level,
      description: p.description ?? '',
      is_active: p.is_active,
    });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.saving.set(true);
    const payload = this.form.getRawValue();
    const p = this.product();
    const req = p
      ? this.api.put(`/products/${p.id}`, payload)
      : this.api.post('/products', payload);

    req.subscribe({
      next: () => {
        this.toast.success(p ? 'Product updated successfully' : 'Product created successfully');
        this.saving.set(false);
        this.saved.emit();
      },
      error: (err: any) => {
        this.toast.error(err?.error?.message ?? 'Failed to save product');
        this.saving.set(false);
      },
    });
  }

  close(): void {
    this.closed.emit();
  }
}
