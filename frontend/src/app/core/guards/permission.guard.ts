import { inject } from '@angular/core';
import { CanActivateFn, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { ToastService } from '../services/toast.service';

export const permissionGuard: CanActivateFn = (route: ActivatedRouteSnapshot) => {
  const auth  = inject(AuthService);
  const toast = inject(ToastService);
  const perm: string | string[] = route.data['permission'];
  const perms = Array.isArray(perm) ? perm : [perm];
  if (auth.hasAnyPermission(perms)) return true;
  toast.error('Access denied.');
  return false;
};
