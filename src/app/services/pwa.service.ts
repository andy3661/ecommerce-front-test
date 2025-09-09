import { Injectable, inject } from '@angular/core';
import { BehaviorSubject, Observable, fromEvent, merge } from 'rxjs';
import { map, startWith } from 'rxjs/operators';
import { DOCUMENT } from '@angular/common';
import { AnalyticsService } from './analytics.service';

export interface PWAInstallPrompt {
  prompt(): Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

export interface NotificationPermission {
  granted: boolean;
  denied: boolean;
  default: boolean;
}

export interface PWACapabilities {
  serviceWorker: boolean;
  notifications: boolean;
  backgroundSync: boolean;
  periodicBackgroundSync: boolean;
  webShare: boolean;
  installPrompt: boolean;
  fullscreen: boolean;
  standalone: boolean;
}

export interface OfflineAction {
  id: string;
  type: 'cart' | 'wishlist' | 'analytics' | 'form';
  data: any;
  timestamp: number;
  synced: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class PWAService {
  private document = inject(DOCUMENT);
  private analyticsService = inject(AnalyticsService);

  // Observables for PWA state
  private isOnlineSubject = new BehaviorSubject<boolean>(navigator.onLine);
  private isInstalledSubject = new BehaviorSubject<boolean>(false);
  private installPromptSubject = new BehaviorSubject<PWAInstallPrompt | null>(null);
  private serviceWorkerSubject = new BehaviorSubject<ServiceWorkerRegistration | null>(null);
  private notificationPermissionSubject = new BehaviorSubject<NotificationPermission>({
    granted: false,
    denied: false,
    default: true
  });

  public isOnline$ = this.isOnlineSubject.asObservable();
  public isInstalled$ = this.isInstalledSubject.asObservable();
  public installPrompt$ = this.installPromptSubject.asObservable();
  public serviceWorker$ = this.serviceWorkerSubject.asObservable();
  public notificationPermission$ = this.notificationPermissionSubject.asObservable();

  // Offline actions queue
  private offlineActions: OfflineAction[] = [];
  private dbName = 'ecommerce-offline-db';
  private dbVersion = 1;

  constructor() {
    this.initializePWA();
    this.setupOnlineDetection();
    this.setupInstallPrompt();
    this.checkIfInstalled();
    this.initializeNotificationPermission();
  }

  // Initialize PWA features
  private async initializePWA(): Promise<void> {
    try {
      // Register service worker
      if ('serviceWorker' in navigator) {
        const registration = await navigator.serviceWorker.register('/sw.js', {
          scope: '/'
        });
        
        this.serviceWorkerSubject.next(registration);
        
        // Handle service worker updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          if (newWorker) {
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                this.showUpdateAvailableNotification();
              }
            });
          }
        });
        
        console.log('✅ Service Worker registered successfully');
        
        // Track PWA initialization
        this.analyticsService.trackEvent({
          event: 'pwa_initialized',
          event_category: 'pwa',
          custom_parameters: {
            service_worker_registered: true,
            capabilities: JSON.stringify(this.getCapabilities())
          }
        });
      }
      
      // Initialize IndexedDB for offline storage
      await this.initializeOfflineDB();
      
      // Setup background sync if supported
      this.setupBackgroundSync();
      
    } catch (error) {
      console.error('❌ Failed to initialize PWA:', error);
    }
  }

  // Setup online/offline detection
  private setupOnlineDetection(): void {
    const online$ = fromEvent(window, 'online').pipe(map(() => true));
    const offline$ = fromEvent(window, 'offline').pipe(map(() => false));
    
    merge(online$, offline$)
      .pipe(startWith(navigator.onLine))
      .subscribe(isOnline => {
        this.isOnlineSubject.next(isOnline);
        
        if (isOnline) {
          this.syncOfflineActions();
        }
        
        // Track connectivity changes
        this.analyticsService.trackEvent({
          event: 'connectivity_changed',
          event_category: 'pwa',
          custom_parameters: {
            is_online: isOnline,
            connection_type: this.getConnectionType()
          }
        });
      });
  }

  // Setup install prompt handling
  private setupInstallPrompt(): void {
    window.addEventListener('beforeinstallprompt', (event: any) => {
      event.preventDefault();
      this.installPromptSubject.next(event as PWAInstallPrompt);
      
      console.log('📱 PWA install prompt available');
      
      // Track install prompt availability
      this.analyticsService.trackEvent({
        event: 'pwa_install_prompt_available',
        event_category: 'pwa'
      });
    });
    
    window.addEventListener('appinstalled', () => {
      this.isInstalledSubject.next(true);
      this.installPromptSubject.next(null);
      
      console.log('✅ PWA installed successfully');
      
      // Track successful installation
      this.analyticsService.trackEvent({
        event: 'pwa_installed',
        event_category: 'pwa',
        custom_parameters: {
          installation_method: 'prompt'
        }
      });
    });
  }

  // Check if PWA is already installed
  private checkIfInstalled(): void {
    // Check if running in standalone mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                        (window.navigator as any).standalone ||
                        this.document.referrer.includes('android-app://');
    
    this.isInstalledSubject.next(isStandalone);
    
    if (isStandalone) {
      console.log('📱 PWA is running in standalone mode');
    }
  }

  // Initialize notification permission state
  private initializeNotificationPermission(): void {
    if ('Notification' in window) {
      const permission = Notification.permission;
      this.notificationPermissionSubject.next({
        granted: permission === 'granted',
        denied: permission === 'denied',
        default: permission === 'default'
      });
    }
  }

  // Install PWA
  async installPWA(): Promise<boolean> {
    const installPrompt = this.installPromptSubject.value;
    
    if (!installPrompt) {
      console.log('❌ No install prompt available');
      return false;
    }
    
    try {
      await installPrompt.prompt();
      const choiceResult = await installPrompt.userChoice;
      
      // Track installation attempt
      this.analyticsService.trackEvent({
        event: 'pwa_install_attempted',
        event_category: 'pwa',
        custom_parameters: {
          user_choice: choiceResult.outcome
        }
      });
      
      if (choiceResult.outcome === 'accepted') {
        console.log('✅ User accepted PWA installation');
        return true;
      } else {
        console.log('❌ User dismissed PWA installation');
        return false;
      }
    } catch (error) {
      console.error('❌ Failed to install PWA:', error);
      return false;
    }
  }

  // Request notification permission
  async requestNotificationPermission(): Promise<boolean> {
    if (!('Notification' in window)) {
      console.log('❌ Notifications not supported');
      return false;
    }
    
    try {
      const permission = await Notification.requestPermission();
      
      this.notificationPermissionSubject.next({
        granted: permission === 'granted',
        denied: permission === 'denied',
        default: permission === 'default'
      });
      
      // Track permission request
      this.analyticsService.trackEvent({
        event: 'notification_permission_requested',
        event_category: 'pwa',
        custom_parameters: {
          permission_result: permission
        }
      });
      
      return permission === 'granted';
    } catch (error) {
      console.error('❌ Failed to request notification permission:', error);
      return false;
    }
  }

  // Show local notification
  async showNotification(title: string, options?: NotificationOptions): Promise<void> {
    const permission = this.notificationPermissionSubject.value;
    
    if (!permission.granted) {
      console.log('❌ Notification permission not granted');
      return;
    }
    
    const defaultOptions = {
      icon: '/assets/icons/icon-192x192.png',
      badge: '/assets/icons/badge-72x72.png',
      vibrate: [200, 100, 200],
      ...options
    } as NotificationOptions;
    
    try {
      const registration = this.serviceWorkerSubject.value;
      
      if (registration) {
        await registration.showNotification(title, defaultOptions);
      } else {
        new Notification(title, defaultOptions);
      }
      
      // Track notification shown
      this.analyticsService.trackEvent({
        event: 'notification_shown',
        event_category: 'pwa',
        custom_parameters: {
          notification_title: title,
          has_service_worker: !!registration
        }
      });
    } catch (error) {
      console.error('❌ Failed to show notification:', error);
    }
  }

  // Add action to offline queue
  addOfflineAction(type: OfflineAction['type'], data: any): void {
    const action: OfflineAction = {
      id: `${type}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      type,
      data,
      timestamp: Date.now(),
      synced: false
    };
    
    this.offlineActions.push(action);
    this.saveOfflineActionsToStorage();
    
    console.log('📝 Offline action queued:', action);
  }

  // Get PWA capabilities
  getCapabilities(): PWACapabilities {
    return {
      serviceWorker: 'serviceWorker' in navigator,
      notifications: 'Notification' in window,
      backgroundSync: 'serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype,
      periodicBackgroundSync: 'serviceWorker' in navigator && 'periodicSync' in window.ServiceWorkerRegistration.prototype,
      webShare: 'share' in navigator,
      installPrompt: !!this.installPromptSubject.value,
      fullscreen: 'requestFullscreen' in document.documentElement,
      standalone: this.isInstalledSubject.value
    };
  }

  // Share content using Web Share API
  async shareContent(data: ShareData): Promise<boolean> {
    if (!('share' in navigator)) {
      console.log('❌ Web Share API not supported');
      return false;
    }
    
    try {
      await navigator.share(data);
      
      // Track successful share
      this.analyticsService.trackEvent({
        event: 'content_shared',
        event_category: 'pwa',
        custom_parameters: {
          share_title: data.title,
          share_url: data.url
        }
      });
      
      return true;
    } catch (error) {
      if ((error as Error).name !== 'AbortError') {
        console.error('❌ Failed to share content:', error);
      }
      return false;
    }
  }

  // Update service worker
  async updateServiceWorker(): Promise<void> {
    const registration = this.serviceWorkerSubject.value;
    
    if (registration) {
      try {
        await registration.update();
        console.log('🔄 Service Worker updated');
        
        // Track service worker update
        this.analyticsService.trackEvent({
          event: 'service_worker_updated',
          event_category: 'pwa'
        });
      } catch (error) {
        console.error('❌ Failed to update Service Worker:', error);
      }
    }
  }

  // Private helper methods
  private async initializeOfflineDB(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.dbVersion);
      
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve();
      
      request.onupgradeneeded = (event) => {
        const db = (event.target as IDBOpenDBRequest).result;
        
        // Create object stores for offline data
        if (!db.objectStoreNames.contains('offline-cart')) {
          db.createObjectStore('offline-cart', { keyPath: 'id' });
        }
        
        if (!db.objectStoreNames.contains('offline-wishlist')) {
          db.createObjectStore('offline-wishlist', { keyPath: 'id' });
        }
        
        if (!db.objectStoreNames.contains('offline-analytics')) {
          db.createObjectStore('offline-analytics', { keyPath: 'id' });
        }
        
        if (!db.objectStoreNames.contains('offline-actions')) {
          db.createObjectStore('offline-actions', { keyPath: 'id' });
        }
      };
    });
  }

  private setupBackgroundSync(): void {
    const registration = this.serviceWorkerSubject.value;
    
    if (registration && 'sync' in window.ServiceWorkerRegistration.prototype) {
      // Register background sync events
      const syncManager = (registration as any).sync;
      if (syncManager) {
        syncManager.register('cart-sync').catch(console.error);
        syncManager.register('analytics-sync').catch(console.error);
        syncManager.register('wishlist-sync').catch(console.error);
        
        console.log('🔄 Background sync registered');
      }
    }
  }

  private async syncOfflineActions(): Promise<void> {
    if (this.offlineActions.length === 0) {
      return;
    }
    
    console.log('🔄 Syncing offline actions...');
    
    for (const action of this.offlineActions) {
      if (!action.synced) {
        try {
          await this.syncAction(action);
          action.synced = true;
        } catch (error) {
          console.error('❌ Failed to sync action:', action, error);
        }
      }
    }
    
    // Remove synced actions
    this.offlineActions = this.offlineActions.filter(action => !action.synced);
    this.saveOfflineActionsToStorage();
  }

  private async syncAction(action: OfflineAction): Promise<void> {
    // Implement specific sync logic based on action type
    switch (action.type) {
      case 'cart':
        // Sync cart data
        break;
      case 'wishlist':
        // Sync wishlist data
        break;
      case 'analytics':
        // Sync analytics events
        break;
      case 'form':
        // Sync form submissions
        break;
    }
  }

  private saveOfflineActionsToStorage(): void {
    try {
      localStorage.setItem('pwa-offline-actions', JSON.stringify(this.offlineActions));
    } catch (error) {
      console.error('❌ Failed to save offline actions:', error);
    }
  }

  private loadOfflineActionsFromStorage(): void {
    try {
      const stored = localStorage.getItem('pwa-offline-actions');
      if (stored) {
        this.offlineActions = JSON.parse(stored);
      }
    } catch (error) {
      console.error('❌ Failed to load offline actions:', error);
      this.offlineActions = [];
    }
  }

  private showUpdateAvailableNotification(): void {
    const options: NotificationOptions & { actions?: any[] } = {
      body: 'Una nueva versión de la aplicación está disponible. Toca para actualizar.',
      tag: 'app-update',
      requireInteraction: true
    };
    
    // Add actions if supported
    if ('actions' in Notification.prototype) {
      (options as any).actions = [
        {
          action: 'update',
          title: 'Actualizar'
        },
        {
          action: 'dismiss',
          title: 'Más tarde'
        }
      ];
    }
    
    this.showNotification('Actualización disponible', options);
  }

  private getConnectionType(): string {
    const connection = (navigator as any).connection || (navigator as any).mozConnection || (navigator as any).webkitConnection;
    return connection ? connection.effectiveType || connection.type || 'unknown' : 'unknown';
  }
}