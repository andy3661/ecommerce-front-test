import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError, BehaviorSubject } from 'rxjs';
import { catchError, retry, timeout, finalize } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface ApiResponse<T = any> {
  data: T;
  message?: string;
  success: boolean;
  errors?: any;
  meta?: {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
  };
}

export interface ApiError {
  message: string;
  status: number;
  errors?: any;
}

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private http = inject(HttpClient);
  private baseUrl = environment.apiUrl;
  private loadingSubject = new BehaviorSubject<boolean>(false);
  
  public loading$ = this.loadingSubject.asObservable();

  constructor() {}

  // GET request
  get<T>(endpoint: string, params?: any): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    let httpParams = new HttpParams();
    if (params) {
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined) {
          httpParams = httpParams.set(key, params[key].toString());
        }
      });
    }

    return this.http.get<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, {
      headers: this.getHeaders(),
      params: httpParams
    }).pipe(
      timeout(environment.apiTimeout),
      retry(1),
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // POST request
  post<T>(endpoint: string, data: any): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    return this.http.post<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, data, {
      headers: this.getHeaders()
    }).pipe(
      timeout(environment.apiTimeout),
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // PUT request
  put<T>(endpoint: string, data: any): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    return this.http.put<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, data, {
      headers: this.getHeaders()
    }).pipe(
      timeout(environment.apiTimeout),
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // DELETE request
  delete<T>(endpoint: string): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    return this.http.delete<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, {
      headers: this.getHeaders()
    }).pipe(
      timeout(environment.apiTimeout),
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // PATCH request
  patch<T>(endpoint: string, data: any): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    return this.http.patch<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, data, {
      headers: this.getHeaders()
    }).pipe(
      timeout(environment.apiTimeout),
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // Upload file
  upload<T>(endpoint: string, formData: FormData): Observable<ApiResponse<T>> {
    this.setLoading(true);
    
    const headers = new HttpHeaders();
    const token = this.getToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
    // Don't set Content-Type for FormData, let browser set it with boundary
    
    return this.http.post<ApiResponse<T>>(`${this.baseUrl}/${endpoint}`, formData, {
      headers
    }).pipe(
      timeout(environment.apiTimeout * 2), // Longer timeout for uploads
      catchError(this.handleError.bind(this)),
      finalize(() => this.setLoading(false))
    );
  }

  // Get headers with authentication
  private getHeaders(): HttpHeaders {
    let headers = new HttpHeaders({
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    });

    const token = this.getToken();
    if (token) {
      headers = headers.set('Authorization', `Bearer ${token}`);
    }

    return headers;
  }

  // Get token from localStorage
  private getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  // Handle HTTP errors
  private handleError(error: HttpErrorResponse): Observable<never> {
    let apiError: ApiError;

    if (error.error instanceof ErrorEvent) {
      // Client-side error
      apiError = {
        message: 'Network error occurred',
        status: 0,
        errors: error.error.message
      };
    } else {
      // Server-side error
      apiError = {
        message: error.error?.message || 'Server error occurred',
        status: error.status,
        errors: error.error?.errors || error.error
      };

      // Handle specific HTTP status codes
      switch (error.status) {
        case 401:
          // Unauthorized - redirect to login
          this.handleUnauthorized();
          break;
        case 403:
          apiError.message = 'Access forbidden';
          break;
        case 404:
          apiError.message = 'Resource not found';
          break;
        case 422:
          apiError.message = 'Validation error';
          break;
        case 500:
          apiError.message = 'Internal server error';
          break;
        default:
          apiError.message = error.error?.message || 'An unexpected error occurred';
      }
    }

    if (environment.enableLogging) {
      console.error('API Error:', apiError);
    }

    return throwError(() => apiError);
  }

  // Handle unauthorized access
  private handleUnauthorized(): void {
    // Clear auth data
    localStorage.removeItem('auth_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user_data');
    
    // Redirect to login (this should be handled by the auth interceptor)
    // window.location.href = '/auth/login';
  }

  // Set loading state
  private setLoading(loading: boolean): void {
    this.loadingSubject.next(loading);
  }

  // Get current loading state
  get isLoading(): boolean {
    return this.loadingSubject.value;
  }

  // Build query string from object
  buildQueryString(params: any): string {
    const queryParams = new URLSearchParams();
    
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined) {
        if (Array.isArray(params[key])) {
          params[key].forEach((value: any) => {
            queryParams.append(`${key}[]`, value.toString());
          });
        } else {
          queryParams.set(key, params[key].toString());
        }
      }
    });
    
    return queryParams.toString();
  }

  // Get full URL for endpoint
  getFullUrl(endpoint: string): string {
    return `${this.baseUrl}/${endpoint}`;
  }
}