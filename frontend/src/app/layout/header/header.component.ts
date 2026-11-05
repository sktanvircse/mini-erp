import { Component, Output, EventEmitter, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [CommonModule],
  template: `
    <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between shrink-0 z-10">
      <button (click)="toggleSidebar.emit()" class="text-gray-500 hover:text-gray-900 p-1">
        ☰
      </button>

      <div class="flex items-center gap-4">
        <!-- User menu -->
        <div class="relative">
          <button (click)="menuOpen.set(!menuOpen())" class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
            <span class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
              {{ (auth.user()?.name ?? 'U')[0].toUpperCase() }}
            </span>
            <span class="hidden sm:block font-medium">{{ auth.user()?.name }}</span>
            <span class="text-gray-400">▾</span>
          </button>

          @if (menuOpen()) {
            <div class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50">
              <div class="px-4 py-2 border-b border-gray-100">
                <p class="font-semibold text-sm">{{ auth.user()?.name }}</p>
                <p class="text-xs text-gray-500">{{ auth.user()?.role_name }}</p>
                <p class="text-xs text-gray-400">{{ auth.user()?.email }}</p>
              </div>
              <button (click)="logout()" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                Sign out
              </button>
            </div>
          }
        </div>
      </div>
    </header>
    <!-- Backdrop -->
    @if (menuOpen()) {
      <div class="fixed inset-0 z-40" (click)="menuOpen.set(false)"></div>
    }
  `
})
export class HeaderComponent {
  @Output() toggleSidebar = new EventEmitter<void>();
  auth = inject(AuthService);
  router = inject(Router);
  menuOpen = signal(false);

  logout() {
    this.menuOpen.set(false);
    this.auth.logout();
  }
}
