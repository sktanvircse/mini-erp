import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ToastComponent } from '../../shared/components/toast/toast.component';
import { SidebarComponent } from '../sidebar/sidebar.component';
import { HeaderComponent } from '../header/header.component';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [CommonModule, RouterOutlet, ToastComponent, SidebarComponent, HeaderComponent],
  template: `
    <div class="flex h-screen w-screen overflow-hidden bg-gray-50">
      <!-- Sidebar -->
      <app-sidebar [collapsed]="sidebarCollapsed()" (toggle)="sidebarCollapsed.set(!sidebarCollapsed())" class="h-full shrink-0 flex flex-col"></app-sidebar>

      <!-- Main area -->
      <div class="flex flex-col flex-1 min-w-0 h-full overflow-hidden">
        <app-header (toggleSidebar)="sidebarCollapsed.set(!sidebarCollapsed())" class="shrink-0"></app-header>
        <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
          <router-outlet></router-outlet>
        </main>
      </div>

      <app-toast></app-toast>
    </div>
  `
})
export class ShellComponent {
  auth = inject(AuthService);
  sidebarCollapsed = signal(false);
}
