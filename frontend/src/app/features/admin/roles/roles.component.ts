import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../../core/services/api.service';
import { ToastService } from '../../../core/services/toast.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-roles',
  standalone: true,
  imports: [CommonModule, FormsModule, PageHeaderComponent],
  template: `
    <app-page-header title="Roles & Permissions Matrix" [breadcrumbs]="[{ label: 'Admin' }, { label: 'Roles' }]">
      <button class="btn btn-primary" (click)="openRoleModal()">+ New Role</button>
    </app-page-header>

    <div class="grid grid-cols-12 gap-6">
      <!-- Roles List -->
      <div class="col-span-4 card">
        <div class="flex justify-between items-center mb-3">
          <h3 class="text-base font-semibold text-gray-900">System Roles</h3>
          <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">{{ roles().length }} roles</span>
        </div>
        <div class="space-y-1">
          @for (role of roles(); track role.id) {
            <button
              (click)="selectRole(role)"
              class="w-full text-left p-3 rounded-lg border transition-colors flex justify-between items-center cursor-pointer hover:bg-gray-50"
              [class.bg-blue-50]="selectedRole?.id === role.id"
              [class.border-blue-500]="selectedRole?.id === role.id"
              [class.border-gray-200]="selectedRole?.id !== role.id">
              <div>
                <div class="flex items-center gap-2">
                  <p class="font-medium text-sm text-gray-900">{{ role.name }}</p>
                  @if (role.is_system) {
                    <span class="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.2 rounded font-semibold">System</span>
                  }
                </div>
                <p class="text-xs text-gray-500 line-clamp-1 mt-0.5">{{ role.description || 'Standard role' }}</p>
              </div>
              <span class="text-gray-400 font-bold ml-2">›</span>
            </button>
          }
        </div>
      </div>

      <!-- Role Info & Permissions Matrix -->
      <div class="col-span-8 card">
        @if (selectedRole) {
          <!-- Role Details Header Banner -->
          <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-lg mb-5">
            <div class="flex flex-wrap justify-between items-start gap-4">
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-lg font-bold text-gray-900">{{ selectedRole.name }}</h3>
                  <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                        [class.bg-purple-100]="selectedRole.is_system"
                        [class.text-purple-800]="selectedRole.is_system"
                        [class.bg-emerald-100]="!selectedRole.is_system"
                        [class.text-emerald-800]="!selectedRole.is_system">
                    {{ selectedRole.is_system ? 'System Default Role' : 'Custom User Role' }}
                  </span>
                </div>
                <p class="text-xs text-gray-600 mt-1">{{ selectedRole.description || 'Manage capabilities and permission grants for this role.' }}</p>
                <div class="flex items-center gap-4 text-xs text-gray-500 mt-2">
                  <span><strong>Role ID:</strong> #{{ selectedRole.id }}</span>
                  <span><strong>Active Permissions:</strong> <span class="font-bold text-blue-700">{{ currentPermissions.size }}</span> of {{ allPermissions().length }} granted</span>
                </div>
              </div>

              <!-- Quick Action Buttons -->
              <div class="flex items-center gap-2">
                <button type="button" class="btn btn-secondary btn-sm text-xs" (click)="selectAll()">Select All</button>
                <button type="button" class="btn btn-secondary btn-sm text-xs" (click)="clearAll()">Deselect All</button>
                <button type="button" class="btn btn-primary btn-sm ml-2" [disabled]="saving()" (click)="savePermissions()">
                  {{ saving() ? 'Saving...' : 'Save Permissions' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Permissions Matrix by Module -->
          <div class="space-y-6 max-h-[65vh] overflow-y-auto pr-2">
            @for (mod of permissionModules(); track mod.module) {
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <div class="flex justify-between items-center bg-gray-50 px-3 py-2 border-b border-gray-200">
                  <h4 class="font-bold text-xs text-gray-700 uppercase tracking-wider">
                    {{ mod.module }}
                  </h4>
                  <span class="text-[11px] text-gray-500">
                    {{ getModuleActiveCount(mod) }} / {{ mod.permissions.length }} active
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-2 p-3 bg-white">
                  @for (perm of mod.permissions; track perm.key) {
                    <label class="flex items-start gap-2.5 p-2 rounded border border-transparent hover:border-gray-200 hover:bg-gray-50 text-xs cursor-pointer select-none"
                           [class.bg-blue-50]="hasPermission(perm.key)"
                           [class.border-blue-200]="hasPermission(perm.key)">
                      <input type="checkbox"
                             [checked]="hasPermission(perm.key)"
                             (change)="togglePermission(perm.key)"
                             class="rounded text-blue-600 mt-0.5 focus:ring-blue-500" />
                      <div class="flex-1">
                        <span class="font-medium text-gray-900 block leading-tight">{{ perm.label }}</span>
                        <span class="text-gray-400 font-mono text-[10px] block mt-0.5">{{ perm.key }}</span>
                        @if (perm.description) {
                          <span class="text-gray-500 text-[11px] block mt-0.5">{{ perm.description }}</span>
                        }
                      </div>
                    </label>
                  }
                </div>
              </div>
            }
          </div>
        } @else {
          <div class="text-center py-20 text-gray-400">
            <svg class="mx-auto h-12 w-12 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <p class="font-medium text-sm">Select a role from the left to view and configure permissions</p>
          </div>
        }
      </div>
    </div>

    <!-- Role Modal -->
    @if (modalOpen()) {
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New Role</h3>
          <form (ngSubmit)="createRole()">
            <div class="mb-4">
              <label class="form-label">Role Name *</label>
              <input type="text" [(ngModel)]="roleForm.name" name="name" required class="form-input" placeholder="e.g. Quality Auditor" />
            </div>
            <div class="mb-6">
              <label class="form-label">Description</label>
              <textarea [(ngModel)]="roleForm.description" name="description" rows="2" class="form-input" placeholder="Role duties & responsibilities"></textarea>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" class="btn btn-secondary" (click)="modalOpen.set(false)">Cancel</button>
              <button type="submit" class="btn btn-primary">Create Role</button>
            </div>
          </form>
        </div>
      </div>
    }
  `
})
export class RolesComponent implements OnInit {
  private api = inject(ApiService);
  private toast = inject(ToastService);

  roles = signal<any[]>([]);
  allPermissions = signal<any[]>([]);
  permissionModules = signal<any[]>([]);

  selectedRole: any = null;
  currentPermissions = new Set<string>();
  modalOpen = signal(false);
  saving = signal(false);

  roleForm = { name: '', description: '' };

  ngOnInit() {
    this.loadPermissions();
    this.loadRoles();
  }

  loadRoles() {
    this.api.get<any>('/admin/roles').subscribe({
      next: (res) => {
        const list = Array.isArray(res) ? res : (res?.data || []);
        this.roles.set(list);
        if (list.length && !this.selectedRole) {
          this.selectRole(list[0]);
        }
      },
      error: () => this.toast.error('Failed to load roles')
    });
  }

  loadPermissions() {
    this.api.get<any>('/admin/roles/permissions').subscribe({
      next: (res) => {
        let raw = res?.data ?? res;
        let list: any[] = [];
        if (Array.isArray(raw)) {
          list = raw;
        } else if (raw?.all && Array.isArray(raw.all)) {
          list = raw.all;
        } else if (raw?.data && Array.isArray(raw.data)) {
          list = raw.data;
        }

        // Normalize permission fields
        list = list.map((p: any) => ({
          ...p,
          key: p.code || p.key || p.name,
          label: p.name || p.label || p.code,
          module: p.module || 'General'
        }));
        this.allPermissions.set(list);

        // Group by module
        const groups = new Map<string, any[]>();
        list.forEach((p: any) => {
          const m = p.module || 'General';
          if (!groups.has(m)) groups.set(m, []);
          groups.get(m)!.push(p);
        });

        const modules = Array.from(groups.entries()).map(([module, permissions]) => ({
          module,
          permissions
        }));
        this.permissionModules.set(modules);

        if (this.selectedRole) {
          this.selectRole(this.selectedRole);
        }
      },
      error: () => this.toast.error('Failed to load system permissions')
    });
  }

  selectRole(role: any) {
    this.selectedRole = role;
    this.api.get<any>(`/admin/roles/${role.id}/permissions`).subscribe({
      next: (res) => {
        let perms = res?.data ?? res;
        if (!Array.isArray(perms) && perms?.data && Array.isArray(perms.data)) {
          perms = perms.data;
        }
        if (!Array.isArray(perms)) perms = [];

        this.currentPermissions = new Set<string>(
          perms.map((p: any) => p.code || p.key || p.name || (typeof p === 'string' ? p : ''))
        );
      },
      error: () => this.toast.error('Failed to load permissions for role')
    });
  }

  hasPermission(key: string): boolean {
    return this.currentPermissions.has(key);
  }

  togglePermission(key: string) {
    if (this.currentPermissions.has(key)) {
      this.currentPermissions.delete(key);
    } else {
      this.currentPermissions.add(key);
    }
  }

  selectAll() {
    this.allPermissions().forEach(p => this.currentPermissions.add(p.key));
  }

  clearAll() {
    this.currentPermissions.clear();
  }

  getModuleActiveCount(mod: any): number {
    return mod.permissions.filter((p: any) => this.hasPermission(p.key)).length;
  }

  savePermissions() {
    if (!this.selectedRole) return;
    this.saving.set(true);
    const permissions = Array.from(this.currentPermissions);
    this.api.put(`/admin/roles/${this.selectedRole.id}/permissions`, { permissions }).subscribe({
      next: () => {
        this.toast.success('Permissions updated successfully!');
        this.saving.set(false);
      },
      error: () => {
        this.toast.error('Failed to update permissions');
        this.saving.set(false);
      }
    });
  }

  openRoleModal() {
    this.roleForm = { name: '', description: '' };
    this.modalOpen.set(true);
  }

  createRole() {
    if (!this.roleForm.name.trim()) return;
    this.api.post('/admin/roles', this.roleForm).subscribe({
      next: () => {
        this.toast.success('Role created successfully');
        this.modalOpen.set(false);
        this.loadRoles();
      },
      error: () => this.toast.error('Failed to create role')
    });
  }
}
