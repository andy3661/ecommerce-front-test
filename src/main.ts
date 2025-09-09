import { bootstrapApplication } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { importProvidersFrom } from '@angular/core';

import { AppComponent } from './app/app.component';
import { routes } from './app/app.routes';
import { environment } from './environments/environment';
import { authInterceptor, csrfInterceptor, rateLimitInterceptor } from './app/interceptors/auth.interceptor';
import { SecurityService } from './app/services/security.service';
import { PWAService } from './app/services/pwa.service';
import { PerformanceService } from './app/services/performance.service';
import { PERFORMANCE_CONFIG, DEFAULT_PERFORMANCE_CONFIG } from './app/config/performance.config';

bootstrapApplication(AppComponent, {
  providers: [
    provideRouter(routes),
    provideHttpClient(
      withInterceptors([
        authInterceptor,
        csrfInterceptor,
        rateLimitInterceptor
      ])
    ),
    provideAnimations(),
    SecurityService,
    PWAService,
    PerformanceService,
    { provide: PERFORMANCE_CONFIG, useValue: DEFAULT_PERFORMANCE_CONFIG },
    // Add other providers as needed
  ]
}).then(() => {
  // Initialize PWA and Performance features after bootstrap
  initializePWA();
  initializePerformanceOptimizations();
}).catch(err => console.error(err));

if (environment.production) {
  // Enable production mode and security features
  console.log('Running in production mode');
}

// PWA initialization function
function initializePWA() {
  // Register service worker
  if ('serviceWorker' in navigator && environment.production) {
    navigator.serviceWorker.register('/sw.js', {
      scope: '/'
    }).then(registration => {
      console.log('✅ Service Worker registered:', registration);
      
      // Check for updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        if (newWorker) {
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              console.log('🔄 New version available');
              // Notify user about update
              if (confirm('Nueva versión disponible. ¿Deseas actualizar?')) {
                window.location.reload();
              }
            }
          });
        }
      });
    }).catch(error => {
      console.error('❌ Service Worker registration failed:', error);
    });
  }
  
  // Add manifest link if not present
  if (!document.querySelector('link[rel="manifest"]')) {
    const manifestLink = document.createElement('link');
    manifestLink.rel = 'manifest';
    manifestLink.href = '/manifest.json';
    document.head.appendChild(manifestLink);
  }
  
  // Add theme color meta tag if not present
  if (!document.querySelector('meta[name="theme-color"]')) {
    const themeColorMeta = document.createElement('meta');
    themeColorMeta.name = 'theme-color';
    themeColorMeta.content = '#1f2937';
    document.head.appendChild(themeColorMeta);
  }
  
  // Add apple touch icon if not present
  if (!document.querySelector('link[rel="apple-touch-icon"]')) {
    const appleTouchIcon = document.createElement('link');
    appleTouchIcon.rel = 'apple-touch-icon';
    appleTouchIcon.href = '/assets/icons/icon-192x192.png';
    document.head.appendChild(appleTouchIcon);
  }
  
  // Add iOS meta tags for better PWA experience
  const iosMetas = [
    { name: 'apple-mobile-web-app-capable', content: 'yes' },
    { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
    { name: 'apple-mobile-web-app-title', content: 'E-Store' }
  ];
  
  iosMetas.forEach(meta => {
    if (!document.querySelector(`meta[name="${meta.name}"]`)) {
      const metaTag = document.createElement('meta');
      metaTag.name = meta.name;
      metaTag.content = meta.content;
      document.head.appendChild(metaTag);
    }
  });
  
  console.log('🚀 PWA initialized successfully');
}

// Performance optimization initialization function
function initializePerformanceOptimizations() {
  // Setup lazy loading for images
  if (typeof window !== 'undefined') {
    import('./app/config/performance.config').then(({ PerformanceOptimizer, DEFAULT_PERFORMANCE_CONFIG }) => {
      // Setup lazy loading
      PerformanceOptimizer.setupLazyLoading(DEFAULT_PERFORMANCE_CONFIG.lazyLoading);
      
      // Setup resource hints
      PerformanceOptimizer.setupResourceHints(DEFAULT_PERFORMANCE_CONFIG.resourceHints);
      
      // Preload critical resources
      PerformanceOptimizer.preloadCriticalResources(DEFAULT_PERFORMANCE_CONFIG.resourceHints.preload);
      
      console.log('⚡ Performance optimizations initialized');
    }).catch(error => {
      console.warn('⚠️ Performance optimization setup failed:', error);
    });
  }
}
