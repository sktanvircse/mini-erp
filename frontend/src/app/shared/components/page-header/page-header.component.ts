import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

export interface Breadcrumb { label: string; link?: string; }

@Component({
  selector: 'app-page-header',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="mb-6">
      @if (breadcrumbs.length) {
        <nav class="mb-2 text-xs text-gray-500 flex items-center gap-1">
          @for (bc of breadcrumbs; track bc.label; let last = $last) {
            @if (bc.link) {
              <a [routerLink]="bc.link" class="hover:text-blue-600">{{ bc.label }}</a>
            } @else {
              <span>{{ bc.label }}</span>
            }
            @if (!last) { <span>›</span> }
          }
        </nav>
      }
      <div class="flex items-center justify-between">
        <h1 class="page-title">{{ title }}</h1>
        <div class="flex gap-2">
          <ng-content></ng-content>
        </div>
      </div>
    </div>
  `
})
export class PageHeaderComponent {
  @Input() title = '';
  @Input() breadcrumbs: Breadcrumb[] = [];
}
