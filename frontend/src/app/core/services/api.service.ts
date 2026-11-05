import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { environment } from '../../../environments/environment';
import { ApiResponse, PaginatedResponse } from '../models/models';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  get<T>(path: string, params?: Record<string, any>): Observable<T> {
    let httpParams = new HttpParams();
    if (params) {
      Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') {
          httpParams = httpParams.set(k, String(v));
        }
      });
    }
    return this.http.get<any>(`${this.base}${path}`, { params: httpParams }).pipe(
      map(r => {
        if (!r.success) throw new Error(r.message);
        if (Array.isArray(r.data) && r.meta) {
          (r.data as any).total = r.meta.total;
          (r.data as any).meta = r.meta;
        }
        return r.data as T;
      }),
      catchError(e => throwError(() => e))
    );
  }

  getList<T>(path: string, params?: Record<string, any>): Observable<PaginatedResponse<T>> {
    return this.get<PaginatedResponse<T>>(path, params);
  }

  post<T>(path: string, body: any): Observable<T> {
    return this.http.post<ApiResponse<T>>(`${this.base}${path}`, body).pipe(
      map(r => { if (!r.success) throw new Error(r.message); return r.data as T; }),
      catchError(e => throwError(() => e))
    );
  }

  put<T>(path: string, body: any): Observable<T> {
    return this.http.put<ApiResponse<T>>(`${this.base}${path}`, body).pipe(
      map(r => { if (!r.success) throw new Error(r.message); return r.data as T; }),
      catchError(e => throwError(() => e))
    );
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<ApiResponse<T>>(`${this.base}${path}`).pipe(
      map(r => { if (!r.success) throw new Error(r.message); return r.data as T; }),
      catchError(e => throwError(() => e))
    );
  }

  patch<T>(path: string, body?: any): Observable<T> {
    return this.http.patch<ApiResponse<T>>(`${this.base}${path}`, body ?? {}).pipe(
      map(r => { if (!r.success) throw new Error(r.message); return r.data as T; }),
      catchError(e => throwError(() => e))
    );
  }
}
