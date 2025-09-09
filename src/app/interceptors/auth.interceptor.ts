import { HttpInterceptorFn, HttpRequest, HttpHandlerFn, HttpEvent, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, switchMap, take } from 'rxjs/operators';
import { throwError, EMPTY, Observable } from 'rxjs';
import { SecurityService } from '../services/security.service';
import { Router } from '@angular/router';

export const authInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> => {
  const securityService = inject(SecurityService);
  const router = inject(Router);

  // Skip auth for certain endpoints
  const skipAuth = [
    '/auth/login',
    '/auth/register',
    '/auth/refresh',
    '/public'
  ].some(path => req.url.includes(path));

  if (skipAuth) {
    return next(req);
  }

  const token = securityService.getToken();
  if (token) {
    const authReq = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`
      }
    });

    return next(authReq).pipe(
      catchError((error: HttpErrorResponse) => {
        if (error.status === 401) {
          // Token expired, logout user
          securityService.logout();
          router.navigate(['/auth/login']);
        }
        
        if (error.status === 403) {
          router.navigate(['/']);
        }
        
        return throwError(() => error);
      })
    );
  }

  return next(req);
};

export const csrfInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> => {
  // Add CSRF token for state-changing requests
  const needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(req.method);
  
  if (needsCsrf) {
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      const csrfReq = req.clone({
        setHeaders: {
          'X-CSRF-Token': csrfToken
        }
      });
      return next(csrfReq);
    }
  }
  
  return next(req);
};

function getCsrfToken(): string | null {
  const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
  return metaTag ? metaTag.content : null;
}

// Rate limiting interceptor
export const rateLimitInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn): Observable<HttpEvent<unknown>> => {
  const now = Date.now();
  const windowMs = 60000; // 1 minute
  const maxRequests = 100;
  
  // Get request history from localStorage
  const requestHistory = JSON.parse(localStorage.getItem('requestHistory') || '[]') as number[];
  
  // Filter requests within the time window
  const recentRequests = requestHistory.filter(timestamp => now - timestamp < windowMs);
  
  if (recentRequests.length >= maxRequests) {
    return throwError(() => new HttpErrorResponse({
      status: 429,
      statusText: 'Too Many Requests',
      error: 'Rate limit exceeded. Please try again later.'
    }));
  }
  
  // Add current request timestamp
  recentRequests.push(now);
  localStorage.setItem('requestHistory', JSON.stringify(recentRequests));
  
  return next(req).pipe(
    catchError(error => {
      if (error.status === 419) {
        // CSRF token mismatch - refresh page to get new token
        window.location.reload();
      }
      return throwError(() => error);
    })
  );
};