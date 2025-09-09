import { Injectable, signal, computed, inject } from '@angular/core';
import { BehaviorSubject, Observable, of, map, catchError } from 'rxjs';
import { ApiService } from './api.service';
import { SecurityService } from './security.service';
import { AnalyticsService } from './analytics.service';
import { EmailService } from './email.service';

export interface UserProfile {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  phone?: string;
  dateOfBirth?: Date;
  gender?: 'male' | 'female' | 'other' | 'prefer_not_to_say';
  avatar?: string;
  preferences: UserPreferences;
  addresses: UserAddress[];
  createdAt: Date;
  updatedAt: Date;
  emailVerified: boolean;
  phoneVerified: boolean;
  twoFactorEnabled: boolean;
}

export interface UserPreferences {
  language: string;
  currency: string;
  timezone: string;
  newsletter: boolean;
  promotions: boolean;
  orderUpdates: boolean;
  theme: 'light' | 'dark' | 'auto';
  notifications: {
    email: boolean;
    sms: boolean;
    push: boolean;
  };
}

export interface UserAddress {
  id: string;
  type: 'shipping' | 'billing' | 'both';
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  postalCode: string;
  country: string;
  phone?: string;
  isDefault: boolean;
}

export interface UpdateProfileData {
  firstName?: string;
  lastName?: string;
  phone?: string;
  dateOfBirth?: Date;
  gender?: string;
}

export interface ChangePasswordData {
  currentPassword: string;
  newPassword: string;
  confirmPassword: string;
}

export interface UserStats {
  totalOrders: number;
  totalSpent: number;
  averageOrderValue: number;
  favoriteCategories: string[];
  memberSince: Date;
  loyaltyPoints: number;
  wishlistItems: number;
}

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private apiService = inject(ApiService);
  private securityService = inject(SecurityService);
  private analyticsService = inject(AnalyticsService);
  private emailService = inject(EmailService);

  // Reactive state
  private profileSubject = new BehaviorSubject<UserProfile | null>(null);
  private addressesSubject = new BehaviorSubject<UserAddress[]>([]);
  private statsSubject = new BehaviorSubject<UserStats | null>(null);
  private loadingSubject = new BehaviorSubject<boolean>(false);

  // Signals
  profile = signal<UserProfile | null>(null);
  addresses = signal<UserAddress[]>([]);
  stats = signal<UserStats | null>(null);
  loading = signal<boolean>(false);

  // Computed values
  fullName = computed(() => {
    const profile = this.profile();
    return profile ? `${profile.firstName} ${profile.lastName}` : '';
  });
  
  defaultAddress = computed(() => 
    this.addresses().find(addr => addr.isDefault) || null
  );
  
  shippingAddresses = computed(() => 
    this.addresses().filter(addr => addr.type === 'shipping' || addr.type === 'both')
  );
  
  billingAddresses = computed(() => 
    this.addresses().filter(addr => addr.type === 'billing' || addr.type === 'both')
  );

  // Observables for legacy compatibility
  profile$ = this.profileSubject.asObservable();
  addresses$ = this.addressesSubject.asObservable();
  stats$ = this.statsSubject.asObservable();
  loading$ = this.loadingSubject.asObservable();

  constructor() {
    // Sync signals with BehaviorSubjects
    this.profileSubject.subscribe(profile => this.profile.set(profile));
    this.addressesSubject.subscribe(addresses => this.addresses.set(addresses));
    this.statsSubject.subscribe(stats => this.stats.set(stats));
    this.loadingSubject.subscribe(loading => this.loading.set(loading));

    // Load user data if authenticated
    if (this.securityService.isAuthenticated) {
      this.loadUserData();
    }

    // Subscribe to auth changes
    this.securityService.authState$.subscribe(authState => {
      if (authState.isAuthenticated) {
        this.loadUserData();
      } else {
        this.clearUserData();
      }
    });
  }

  // Get user profile
  getProfile(): Observable<UserProfile | null> {
    this.setLoading(true);
    
    return this.apiService.get<UserProfile>('user/profile').pipe(
      map(response => {
        const profile = response.data || null;
        this.profileSubject.next(profile);
        this.setLoading(false);
        return profile;
      }),
      catchError(error => {
        console.error('Error loading profile:', error);
        this.setLoading(false);
        return of(null);
      })
    );
  }

  // Update user profile
  updateProfile(data: UpdateProfileData): Observable<{ success: boolean; profile?: UserProfile; message: string }> {
    this.setLoading(true);
    
    return this.apiService.put<UserProfile>('user/profile', data).pipe(
      map(response => {
        this.setLoading(false);
        
        if (response.success && response.data) {
          const profile = response.data;
          this.profileSubject.next(profile);
          
          // Track profile update
          this.analyticsService.trackEvent('profile_updated', {
            fields_updated: Object.keys(data)
          });
          
          return {
            success: true,
            profile,
            message: response.message || 'Profile updated successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to update profile'
        };
      }),
      catchError(error => {
        this.setLoading(false);
        console.error('Error updating profile:', error);
        return of({
          success: false,
          message: error.message || 'Failed to update profile'
        });
      })
    );
  }

  // Change password
  changePassword(data: ChangePasswordData): Observable<{ success: boolean; message: string }> {
    if (data.newPassword !== data.confirmPassword) {
      return of({
        success: false,
        message: 'New passwords do not match'
      });
    }

    const requestData = {
      current_password: data.currentPassword,
      new_password: data.newPassword,
      new_password_confirmation: data.confirmPassword
    };
    
    return this.apiService.put<any>('user/password', requestData).pipe(
      map(response => {
        if (response.success) {
          // Track password change
          this.analyticsService.trackEvent('password_changed');
          
          // Send notification email
          const profile = this.profileSubject.value;
          if (profile?.email) {
            this.emailService.sendPasswordChangeNotification(profile.email).subscribe();
          }
        }
        
        return {
          success: response.success,
          message: response.message || 'Password changed successfully'
        };
      }),
      catchError(error => {
        console.error('Error changing password:', error);
        return of({
          success: false,
          message: error.message || 'Failed to change password'
        });
      })
    );
  }

  // Upload avatar
  uploadAvatar(file: File): Observable<{ success: boolean; avatarUrl?: string; message: string }> {
    this.setLoading(true);
    
    return this.apiService.upload('user/avatar', file).pipe(
      map(response => {
        this.setLoading(false);
        
        if (response.success && response.data) {
          // Update profile with new avatar
          const currentProfile = this.profileSubject.value;
          if (currentProfile) {
            const updatedProfile = {
              ...currentProfile,
              avatar: response.data.avatar_url
            };
            this.profileSubject.next(updatedProfile);
          }
          
          // Track avatar upload
          this.analyticsService.trackEvent('avatar_uploaded');
          
          return {
            success: true,
            avatarUrl: response.data.avatar_url,
            message: response.message || 'Avatar uploaded successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to upload avatar'
        };
      }),
      catchError(error => {
        this.setLoading(false);
        console.error('Error uploading avatar:', error);
        return of({
          success: false,
          message: error.message || 'Failed to upload avatar'
        });
      })
    );
  }

  // Get user addresses
  getAddresses(): Observable<UserAddress[]> {
    return this.apiService.get<UserAddress[]>('user/addresses').pipe(
      map(response => {
        const addresses = response.data || [];
        this.addressesSubject.next(addresses);
        return addresses;
      }),
      catchError(error => {
        console.error('Error loading addresses:', error);
        return of([]);
      })
    );
  }

  // Add new address
  addAddress(address: Omit<UserAddress, 'id'>): Observable<{ success: boolean; address?: UserAddress; message: string }> {
    return this.apiService.post<UserAddress>('user/addresses', address).pipe(
      map(response => {
        if (response.success && response.data) {
          const newAddress = response.data;
          const currentAddresses = this.addressesSubject.value;
          
          // If this is set as default, remove default from others
          let updatedAddresses = currentAddresses;
          if (newAddress.isDefault) {
            updatedAddresses = currentAddresses.map(addr => ({ ...addr, isDefault: false }));
          }
          
          this.addressesSubject.next([...updatedAddresses, newAddress]);
          
          // Track address addition
          this.analyticsService.trackEvent('address_added', {
            address_type: newAddress.type,
            is_default: newAddress.isDefault
          });
          
          return {
            success: true,
            address: newAddress,
            message: response.message || 'Address added successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to add address'
        };
      }),
      catchError(error => {
        console.error('Error adding address:', error);
        return of({
          success: false,
          message: error.message || 'Failed to add address'
        });
      })
    );
  }

  // Update address
  updateAddress(addressId: string, address: Partial<UserAddress>): Observable<{ success: boolean; address?: UserAddress; message: string }> {
    return this.apiService.put<UserAddress>(`user/addresses/${addressId}`, address).pipe(
      map(response => {
        if (response.success && response.data) {
          const updatedAddress = response.data;
          let currentAddresses = this.addressesSubject.value;
          
          // If this is set as default, remove default from others
          if (updatedAddress.isDefault) {
            currentAddresses = currentAddresses.map(addr => 
              addr.id === addressId ? addr : { ...addr, isDefault: false }
            );
          }
          
          // Update the specific address
          const updatedAddresses = currentAddresses.map(addr => 
            addr.id === addressId ? updatedAddress : addr
          );
          
          this.addressesSubject.next(updatedAddresses);
          
          // Track address update
          this.analyticsService.trackEvent('address_updated', {
            address_id: addressId,
            is_default: updatedAddress.isDefault
          });
          
          return {
            success: true,
            address: updatedAddress,
            message: response.message || 'Address updated successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to update address'
        };
      }),
      catchError(error => {
        console.error('Error updating address:', error);
        return of({
          success: false,
          message: error.message || 'Failed to update address'
        });
      })
    );
  }

  // Delete address
  deleteAddress(addressId: string): Observable<{ success: boolean; message: string }> {
    return this.apiService.delete<any>(`user/addresses/${addressId}`).pipe(
      map(response => {
        if (response.success) {
          const currentAddresses = this.addressesSubject.value;
          const updatedAddresses = currentAddresses.filter(addr => addr.id !== addressId);
          this.addressesSubject.next(updatedAddresses);
          
          // Track address deletion
          this.analyticsService.trackEvent('address_deleted', {
            address_id: addressId
          });
        }
        
        return {
          success: response.success,
          message: response.message || 'Address deleted successfully'
        };
      }),
      catchError(error => {
        console.error('Error deleting address:', error);
        return of({
          success: false,
          message: error.message || 'Failed to delete address'
        });
      })
    );
  }

  // Set default address
  setDefaultAddress(addressId: string): Observable<{ success: boolean; message: string }> {
    return this.apiService.put<any>(`user/addresses/${addressId}/default`, {}).pipe(
      map(response => {
        if (response.success) {
          const currentAddresses = this.addressesSubject.value;
          const updatedAddresses = currentAddresses.map(addr => ({
            ...addr,
            isDefault: addr.id === addressId
          }));
          this.addressesSubject.next(updatedAddresses);
          
          // Track default address change
          this.analyticsService.trackEvent('default_address_changed', {
            address_id: addressId
          });
        }
        
        return {
          success: response.success,
          message: response.message || 'Default address updated successfully'
        };
      }),
      catchError(error => {
        console.error('Error setting default address:', error);
        return of({
          success: false,
          message: error.message || 'Failed to set default address'
        });
      })
    );
  }

  // Update preferences
  updatePreferences(preferences: Partial<UserPreferences>): Observable<{ success: boolean; preferences?: UserPreferences; message: string }> {
    return this.apiService.put<UserPreferences>('user/preferences', preferences).pipe(
      map(response => {
        if (response.success && response.data) {
          const updatedPreferences = response.data;
          const currentProfile = this.profileSubject.value;
          
          if (currentProfile) {
            const updatedProfile = {
              ...currentProfile,
              preferences: updatedPreferences
            };
            this.profileSubject.next(updatedProfile);
          }
          
          // Track preferences update
          this.analyticsService.trackEvent('preferences_updated', {
            updated_fields: Object.keys(preferences)
          });
          
          return {
            success: true,
            preferences: updatedPreferences,
            message: response.message || 'Preferences updated successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to update preferences'
        };
      }),
      catchError(error => {
        console.error('Error updating preferences:', error);
        return of({
          success: false,
          message: error.message || 'Failed to update preferences'
        });
      })
    );
  }

  // Get user statistics
  getStats(): Observable<UserStats | null> {
    return this.apiService.get<UserStats>('user/stats').pipe(
      map(response => {
        const stats = response.data || null;
        this.statsSubject.next(stats);
        return stats;
      }),
      catchError(error => {
        console.error('Error loading user stats:', error);
        return of(null);
      })
    );
  }

  // Verify email
  verifyEmail(token: string): Observable<{ success: boolean; message: string }> {
    return this.apiService.post<any>('user/verify-email', { token }).pipe(
      map(response => {
        if (response.success) {
          // Update profile to reflect email verification
          const currentProfile = this.profileSubject.value;
          if (currentProfile) {
            const updatedProfile = {
              ...currentProfile,
              emailVerified: true
            };
            this.profileSubject.next(updatedProfile);
          }
          
          // Track email verification
          this.analyticsService.trackEvent('email_verified');
        }
        
        return {
          success: response.success,
          message: response.message || 'Email verified successfully'
        };
      }),
      catchError(error => {
        console.error('Error verifying email:', error);
        return of({
          success: false,
          message: error.message || 'Failed to verify email'
        });
      })
    );
  }

  // Resend verification email
  resendVerificationEmail(): Observable<{ success: boolean; message: string }> {
    return this.apiService.post<any>('user/resend-verification', {}).pipe(
      map(response => ({
        success: response.success,
        message: response.message || 'Verification email sent successfully'
      })),
      catchError(error => {
        console.error('Error resending verification email:', error);
        return of({
          success: false,
          message: error.message || 'Failed to send verification email'
        });
      })
    );
  }

  // Enable/disable two-factor authentication
  toggleTwoFactor(enable: boolean, code?: string): Observable<{ success: boolean; qrCode?: string; backupCodes?: string[]; message: string }> {
    const endpoint = enable ? 'user/2fa/enable' : 'user/2fa/disable';
    const data = enable ? {} : { code };
    
    return this.apiService.post<{ qr_code?: string; backup_codes?: string[] }>(endpoint, data).pipe(
      map(response => {
        if (response.success) {
          // Update profile to reflect 2FA status
          const currentProfile = this.profileSubject.value;
          if (currentProfile) {
            const updatedProfile = {
              ...currentProfile,
              twoFactorEnabled: enable
            };
            this.profileSubject.next(updatedProfile);
          }
          
          // Track 2FA change
          this.analyticsService.trackEvent('two_factor_toggled', {
            enabled: enable
          });
          
          return {
            success: true,
            qrCode: response.data?.qr_code,
            backupCodes: response.data?.backup_codes,
            message: response.message || `Two-factor authentication ${enable ? 'enabled' : 'disabled'} successfully`
          };
        }
        
        return {
          success: false,
          message: response.message || `Failed to ${enable ? 'enable' : 'disable'} two-factor authentication`
        };
      }),
      catchError(error => {
        console.error('Error toggling two-factor authentication:', error);
        return of({
          success: false,
          message: error.message || `Failed to ${enable ? 'enable' : 'disable'} two-factor authentication`
        });
      })
    );
  }

  // Delete account
  deleteAccount(password: string): Observable<{ success: boolean; message: string }> {
    return this.apiService.delete<any>('user/account', { password }).pipe(
      map(response => {
        if (response.success) {
          // Track account deletion
          this.analyticsService.trackEvent('account_deleted');
          
          // Clear user data and logout
          this.clearUserData();
          this.securityService.logout();
        }
        
        return {
          success: response.success,
          message: response.message || 'Account deleted successfully'
        };
      }),
      catchError(error => {
        console.error('Error deleting account:', error);
        return of({
          success: false,
          message: error.message || 'Failed to delete account'
        });
      })
    );
  }

  // Private helper methods
  private loadUserData(): void {
    this.getProfile().subscribe();
    this.getAddresses().subscribe();
    this.getStats().subscribe();
  }

  private clearUserData(): void {
    this.profileSubject.next(null);
    this.addressesSubject.next([]);
    this.statsSubject.next(null);
  }

  private setLoading(loading: boolean): void {
    this.loadingSubject.next(loading);
  }

  // Utility methods
  formatAddress(address: UserAddress): string {
    const parts = [
      address.address1,
      address.address2,
      address.city,
      address.state,
      address.postalCode,
      address.country
    ].filter(Boolean);
    
    return parts.join(', ');
  }

  getAddressLabel(address: UserAddress): string {
    const name = `${address.firstName} ${address.lastName}`;
    const type = address.type.charAt(0).toUpperCase() + address.type.slice(1);
    const defaultText = address.isDefault ? ' (Default)' : '';
    
    return `${name} - ${type}${defaultText}`;
  }

  isProfileComplete(): boolean {
    const profile = this.profile();
    if (!profile) return false;
    
    return !!(profile.firstName && profile.lastName && profile.email);
  }

  getCompletionPercentage(): number {
    const profile = this.profile();
    if (!profile) return 0;
    
    const fields = [
      profile.firstName,
      profile.lastName,
      profile.email,
      profile.phone,
      profile.dateOfBirth,
      profile.avatar
    ];
    
    const completedFields = fields.filter(Boolean).length;
    return Math.round((completedFields / fields.length) * 100);
  }
}