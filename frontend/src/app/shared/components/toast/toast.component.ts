import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService } from '../../../core/services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-80">
      @for (t of toast.toasts(); track t.id) {
        <div [class]="toastClass(t.type)" class="flex items-start gap-3 p-4 rounded-lg shadow-lg animate-pulse-once">
          <span class="text-lg">{{ icon(t.type) }}</span>
          <p class="text-sm flex-1">{{ t.message }}</p>
          <button (click)="toast.remove(t.id)" class="text-current opacity-60 hover:opacity-100">✕</button>
        </div>
      }
    </div>
  `
})
export class ToastComponent {
  toast = inject(ToastService);

  toastClass(type: string) {
    const map: Record<string, string> = {
      success: 'bg-green-600 text-white',
      error:   'bg-red-600 text-white',
      info:    'bg-blue-600 text-white',
      warning: 'bg-yellow-500 text-white',
    };
    return map[type] ?? 'bg-gray-800 text-white';
  }

  icon(type: string) {
    return { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' }[type] ?? '•';
  }
}
