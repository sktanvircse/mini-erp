import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { tap, map, catchError } from 'rxjs/operators';
import { Observable, throwError } from 'rxjs';
import { environment } from '../../../environments/environment';
import { User, ApiResponse } from '../models/models';

interface AuthResponse {
  token: string;
  user: User;
  expires_in: number;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private _user = signal<User | null>(null);
  private _token = signal<string | null>(null);

  readonly user = this._user.asReadonly();
  readonly token = this._token.asReadonly();
  readonly isLoggedIn = computed(() => !!this._token());

  constructor(private http: HttpClient, private router: Router) {
    this.loadFromStorage();
  }

  private loadFromStorage(): void {
    const token = localStorage.getItem('erp_token');
    const user  = localStorage.getItem('erp_user');
    if (token && user) {
      this._token.set(token);
      this._user.set(JSON.parse(user));
    }
  }

  login(email: string, password: string): Observable<AuthResponse> {
    return this.http.post<ApiResponse<AuthResponse>>(
      `${environment.apiUrl}/auth/login`,
      { email, password }
    ).pipe(
      map(res => {
        if (!res.success || !res.data) throw new Error(res.message || 'Login failed');
        return res.data;
      }),
      tap(data => {
        localStorage.setItem('erp_token', data.token);
        localStorage.setItem('erp_user', JSON.stringify(data.user));
        this._token.set(data.token);
        this._user.set(data.user);
      })
    );
  }

  logout(): void {
    localStorage.removeItem('erp_token');
    localStorage.removeItem('erp_user');
    this._token.set(null);
    this._user.set(null);
    this.router.navigate(['/login']);
  }

  me(): Observable<User> {
    return this.http.get<ApiResponse<User>>(`${environment.apiUrl}/auth/me`).pipe(
      map(res => {
        if (!res.success || !res.data) throw new Error(res.message);
        localStorage.setItem('erp_user', JSON.stringify(res.data));
        this._user.set(res.data);
        return res.data;
      })
    );
  }

  changePassword(current: string, newPass: string, confirm: string): Observable<any> {
    return this.http.post(`${environment.apiUrl}/auth/change-password`, {
      current_password: current, new_password: newPass, new_password_confirmation: confirm
    });
  }

  hasPermission(permission: string): boolean {
    const u = this._user();
    if (!u) return false;
    if (u.role_name === 'Admin') return true;
    return u.permissions.includes(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some(p => this.hasPermission(p));
  }
}
