import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map, take } from 'rxjs/operators';
import { SecurityService } from '../services/security.service';

export const authGuard: CanActivateFn = (route, state) => {
  const securityService = inject(SecurityService);
  const router = inject(Router);

  return securityService.authState$.pipe(
    take(1),
    map(authState => {
      if (authState.isAuthenticated) {
        return true;
      } else {
        // Store the attempted URL for redirecting after login
        const returnUrl = state.url;
        router.navigate(['/auth/login'], { 
          queryParams: { returnUrl },
          replaceUrl: true 
        });
        return false;
      }
    })
  );
};

export const guestGuard: CanActivateFn = (route, state) => {
  const securityService = inject(SecurityService);
  const router = inject(Router);

  return securityService.authState$.pipe(
    take(1),
    map(authState => {
      if (!authState.isAuthenticated) {
        return true;
      } else {
        // Redirect authenticated users away from auth pages
        router.navigate(['/account/dashboard'], { replaceUrl: true });
        return false;
      }
    })
  );
};

export const adminGuard: CanActivateFn = (route, state) => {
  const securityService = inject(SecurityService);
  const router = inject(Router);

  return securityService.authState$.pipe(
    take(1),
    map(authState => {
      if (authState.isAuthenticated && authState.user?.role === 'admin') {
        return true;
      } else if (authState.isAuthenticated) {
        // User is authenticated but not admin
        router.navigate(['/'], { replaceUrl: true });
        return false;
      } else {
        // User is not authenticated
        const returnUrl = state.url;
        router.navigate(['/auth/login'], { 
          queryParams: { returnUrl },
          replaceUrl: true 
        });
        return false;
      }
    })
  );
};