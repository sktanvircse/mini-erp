import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-status-badge',
  standalone: true,
  imports: [CommonModule],
  template: `<span [class]="badgeClass">{{ label }}</span>`
})
export class StatusBadgeComponent {
  @Input() status = '';

  get label() { return this.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()); }

  get badgeClass() {
    const map: Record<string, string> = {
      active: 'badge-green', approved: 'badge-green', paid: 'badge-green',
      posted: 'badge-green', present: 'badge-green', received: 'badge-green',
      confirmed: 'badge-blue', partial: 'badge-blue', submitted: 'badge-blue',
      draft: 'badge-gray', pending: 'badge-yellow', half_day: 'badge-yellow',
      open: 'badge-blue', in_transit: 'badge-yellow',
      rejected: 'badge-red', cancelled: 'badge-red', inactive: 'badge-red',
      absent: 'badge-red', terminated: 'badge-red',
      delivered: 'badge-green', closed: 'badge-gray',
    };
    return map[this.status?.toLowerCase()] ?? 'badge-gray';
  }
}
