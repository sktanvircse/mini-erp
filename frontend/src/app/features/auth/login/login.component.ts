import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { ToastService } from '../../../core/services/toast.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-blue-700 p-4">
      <div class="w-full max-w-sm">
        <div class="text-center mb-8">
          <div class="text-5xl mb-3">📦</div>
          <h1 class="text-3xl font-bold text-white">Mini ERP</h1>
          <p class="text-blue-200 text-sm mt-1">Enterprise Resource Planning</p>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl p-8">
          <h2 class="text-xl font-semibold text-gray-900 mb-6 text-center">Sign in to your account</h2>

          <form [formGroup]="form" (ngSubmit)="submit()">
            <div class="mb-4">
              <label class="form-label">Email address</label>
              <input formControlName="email" type="email" class="form-input"
                placeholder="admin@minierp.com" autocomplete="email" />
              @if (form.get('email')?.invalid && form.get('email')?.touched) {
                <p class="text-red-500 text-xs mt-1">Valid email required.</p>
              }
            </div>

            <div class="mb-6">
              <label class="form-label">Password</label>
              <input formControlName="password" [type]="showPass ? 'text' : 'password'" class="form-input"
                placeholder="••••••••" autocomplete="current-password" />
              <button type="button" (click)="showPass = !showPass" class="text-xs text-blue-600 hover:underline mt-1">
                {{ showPass ? 'Hide' : 'Show' }} password
              </button>
            </div>

            @if (error()) {
              <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">
                {{ error() }}
              </div>
            }

            <button type="submit" [disabled]="loading()" class="btn btn-primary w-full justify-center py-3">
              @if (loading()) { <span class="animate-spin mr-2">⏳</span> } Sign in
            </button>
          </form>

          <div class="mt-6 p-3 bg-gray-50 rounded-lg text-xs text-gray-500">
            <p class="font-medium mb-1">Demo credentials:</p>
            <p>admin@minierp.com / Admin@123</p>
          </div>
        </div>
      </div>
    </div>
  `
})
export class LoginComponent {
  private fb   = inject(FormBuilder);
  private auth = inject(AuthService);
  private router = inject(Router);

  form = this.fb.group({
    email:    ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  loading = signal(false);
  error   = signal('');
  showPass = false;

  submit() {
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.loading.set(true);
    this.error.set('');
    const { email, password } = this.form.value;
    this.auth.login(email!, password!).subscribe({
      next: () => this.router.navigate(['/dashboard']),
      error: (e) => {
        this.error.set(e?.error?.message || 'Invalid credentials.');
        this.loading.set(false);
      }
    });
  }
}
