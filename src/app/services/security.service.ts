import { Injectable, inject } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { Router } from '@angular/router';
import { EmailService } from './email.service';
import { AnalyticsService } from './analytics.service';
import { ApiService, ApiResponse } from './api.service';
import { map, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

export interface User {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  role: 'user' | 'admin';
  isEmailVerified: boolean;
  createdAt: Date;
  lastLoginAt?: Date;
}

export interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  token: string | null;
  refreshToken: string | null;
}

export interface LoginCredentials {
  email: string;
  password: string;
  rememberMe?: boolean;
}

export interface RegisterData {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
  acceptTerms: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class SecurityService {
  private router = inject(Router);
  private emailService = inject(EmailService);
  private analyticsService = inject(AnalyticsService);
  private apiService = inject(ApiService);
  
  private readonly TOKEN_KEY = 'auth_token';
  private readonly REFRESH_TOKEN_KEY = 'refresh_token';
  private readonly USER_KEY = 'user_data';
  
  private authState = new BehaviorSubject<AuthState>({
    isAuthenticated: false,
    user: null,
    token: null,
    refreshToken: null
  });
  
  public authState$ = this.authState.asObservable();
  
  constructor() {
    this.initializeAuth();
    this.setupCSP();
  }
  
  initializeAuth(): void {
    try {
      const token = this.getStoredToken();
      const user = this.getStoredUser();
      const refreshToken = this.getStoredRefreshToken();
      
      if (token && user && this.isTokenValid(token)) {
        this.authState.next({
          isAuthenticated: true,
          user,
          token,
          refreshToken
        });
      } else {
        this.clearAuthData();
      }
    } catch (error) {
      console.error('Error initializing auth:', error);
      this.clearAuthData();
    }
  }
  
  private setupCSP(): void {
    // Set up Content Security Policy meta tag
    const existingCSP = document.querySelector('meta[http-equiv="Content-Security-Policy"]');
    if (!existingCSP) {
      const cspMeta = document.createElement('meta');
      cspMeta.httpEquiv = 'Content-Security-Policy';
      cspMeta.content = this.getCSPDirectives();
      document.head.appendChild(cspMeta);
    }
  }
  
  private getCSPDirectives(): string {
    return [
      "default-src 'self'",
      "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google-analytics.com https://www.googletagmanager.com",
      "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
      "font-src 'self' https://fonts.gstatic.com",
      "img-src 'self' data: https: blob:",
      "connect-src 'self' https://api.stripe.com https://www.google-analytics.com",
      "frame-src 'self' https://js.stripe.com",
      "object-src 'none'",
      "base-uri 'self'",
      "form-action 'self'",
      "upgrade-insecure-requests"
    ].join('; ');
  }
  
  // Public Methods
  getToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }

  getCurrentUser() {
    return this.authState.value.user;
  }

  async login(credentials: LoginCredentials): Promise<{ success: boolean; error?: string }> {
    try {
      const response = await this.apiService.post<{
        user: User;
        token: string;
        refresh_token?: string;
      }>('auth/login', {
        email: credentials.email,
        password: credentials.password,
        remember_me: credentials.rememberMe
      }).toPromise();
      
      if (response?.success && response.data.user && response.data.token) {
        this.setAuthData(response.data.user, response.data.token, response.data.refresh_token);
        
        // Track login event
        this.analyticsService.trackEvent({
          event: 'login',
          event_category: 'engagement',
          event_label: 'user_login',
          custom_parameters: {
            method: 'email',
            user_id: response.data.user.id
          }
        });
        
        return { success: true };
      }
      
      return { success: false, error: response?.message || 'Login failed' };
    } catch (error: any) {
      console.error('Login error:', error);
      return { success: false, error: error.message || 'Network error occurred' };
    }
  }
  
  async register(data: RegisterData): Promise<{ success: boolean; error?: string }> {
    try {
      // Validate input data
      const validation = this.validateRegistrationData(data);
      if (!validation.isValid) {
        return { success: false, error: validation.error };
      }
      
      const response = await this.apiService.post<{
        user: User;
        token: string;
        refresh_token?: string;
      }>('auth/register', {
        email: data.email,
        password: data.password,
        password_confirmation: data.password,
        first_name: data.firstName,
        last_name: data.lastName,
        accept_terms: data.acceptTerms
      }).toPromise();
      
      if (response?.success && response.data.user && response.data.token) {
        this.setAuthData(response.data.user, response.data.token, response.data.refresh_token);
        
        // Send welcome email
        this.emailService.sendWelcomeEmail(response.data.user.email, response.data.user.firstName).subscribe({
          next: () => console.log('Welcome email sent successfully'),
          error: (error) => console.error('Failed to send welcome email:', error)
        });
        
        // Track registration event
        this.analyticsService.trackEvent({
          event: 'sign_up',
          event_category: 'engagement',
          event_label: 'user_registration',
          custom_parameters: {
            method: 'email',
            user_id: response.data.user.id
          }
        });
        
        return { success: true };
      }
      
      return { success: false, error: response?.message || 'Registration failed' };
    } catch (error: any) {
      console.error('Registration error:', error);
      return { success: false, error: error.message || 'Network error occurred' };
    }
  }
  
  async logout(): Promise<void> {
    try {
      // Call backend logout endpoint
      await this.apiService.post('auth/logout', {}).toPromise();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      this.clearAuthData();
      this.router.navigate(['/']);
    }
  }
  
  async refreshToken(): Promise<boolean> {
    try {
      const refreshToken = this.getStoredRefreshToken();
      if (!refreshToken) {
        return false;
      }
      
      const response = await this.apiService.post<{
        token: string;
        refresh_token?: string;
      }>('auth/refresh', {
        refresh_token: refreshToken
      }).toPromise();
      
      if (response?.success && response.data.token) {
        const currentState = this.authState.value;
        this.authState.next({
          ...currentState,
          token: response.data.token,
          refreshToken: response.data.refresh_token || refreshToken
        });
        this.storeToken(response.data.token);
        if (response.data.refresh_token) {
          this.storeRefreshToken(response.data.refresh_token);
        }
        return true;
      }
      
      return false;
    } catch (error) {
      console.error('Token refresh error:', error);
      return false;
    }
  }
  
  // Security Utilities
  sanitizeInput(input: string): string {
    return input
      .replace(/[<>"'&]/g, (match) => {
        const entities: { [key: string]: string } = {
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#x27;',
          '&': '&amp;'
        };
        return entities[match];
      })
      .trim();
  }
  
  validateEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
  
  validatePassword(password: string): { isValid: boolean; errors: string[] } {
    const errors: string[] = [];
    
    if (password.length < 8) {
      errors.push('Password must be at least 8 characters long');
    }
    
    if (!/[A-Z]/.test(password)) {
      errors.push('Password must contain at least one uppercase letter');
    }
    
    if (!/[a-z]/.test(password)) {
      errors.push('Password must contain at least one lowercase letter');
    }
    
    if (!/\d/.test(password)) {
      errors.push('Password must contain at least one number');
    }
    
    if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
      errors.push('Password must contain at least one special character');
    }
    
    return {
      isValid: errors.length === 0,
      errors
    };
  }
  
  // Private Helper Methods
  private setAuthData(user: User, token: string, refreshToken?: string): void {
    this.storeToken(token);
    this.storeUser(user);
    if (refreshToken) {
      this.storeRefreshToken(refreshToken);
    }
    
    this.authState.next({
      isAuthenticated: true,
      user,
      token,
      refreshToken: refreshToken || null
    });
  }
  
  private clearAuthData(): void {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
    localStorage.removeItem(this.REFRESH_TOKEN_KEY);
    
    this.authState.next({
      isAuthenticated: false,
      user: null,
      token: null,
      refreshToken: null
    });
  }
  
  private storeToken(token: string): void {
    localStorage.setItem(this.TOKEN_KEY, token);
  }
  
  private storeUser(user: User): void {
    localStorage.setItem(this.USER_KEY, JSON.stringify(user));
  }
  
  private storeRefreshToken(refreshToken: string): void {
    localStorage.setItem(this.REFRESH_TOKEN_KEY, refreshToken);
  }
  
  private getStoredToken(): string | null {
    return localStorage.getItem(this.TOKEN_KEY);
  }
  
  private getStoredUser(): User | null {
    try {
      const userData = localStorage.getItem(this.USER_KEY);
      return userData ? JSON.parse(userData) : null;
    } catch {
      return null;
    }
  }
  
  private getStoredRefreshToken(): string | null {
    return localStorage.getItem(this.REFRESH_TOKEN_KEY);
  }
  
  private isTokenValid(token: string): boolean {
    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      const currentTime = Math.floor(Date.now() / 1000);
      return payload.exp > currentTime;
    } catch {
      return false;
    }
  }
  
  private validateRegistrationData(data: RegisterData): { isValid: boolean; error?: string } {
    if (!data.email || !this.validateEmail(data.email)) {
      return { isValid: false, error: 'Please enter a valid email address' };
    }
    
    const passwordValidation = this.validatePassword(data.password);
    if (!passwordValidation.isValid) {
      return { isValid: false, error: passwordValidation.errors[0] };
    }
    
    if (!data.firstName?.trim()) {
      return { isValid: false, error: 'First name is required' };
    }
    
    if (!data.lastName?.trim()) {
      return { isValid: false, error: 'Last name is required' };
    }
    
    if (!data.acceptTerms) {
      return { isValid: false, error: 'You must accept the terms and conditions' };
    }
    
    return { isValid: true };
  }
  
  // Mock API methods - replace with actual backend calls
  private async mockLogin(credentials: LoginCredentials): Promise<any> {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Mock successful login
    if (credentials.email === 'demo@example.com' && credentials.password === 'Demo123!') {
      return {
        success: true,
        user: {
          id: '1',
          email: credentials.email,
          firstName: 'Demo',
          lastName: 'User',
          role: 'user' as const,
          isEmailVerified: true,
          createdAt: new Date(),
          lastLoginAt: new Date()
        },
        token: this.generateMockToken(),
        refreshToken: this.generateMockRefreshToken()
      };
    }
    
    return {
      success: false,
      error: 'Invalid email or password'
    };
  }
  
  private async mockRegister(data: RegisterData): Promise<any> {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1500));
    
    // Mock successful registration
    return {
      success: true,
      user: {
        id: Date.now().toString(),
        email: data.email,
        firstName: data.firstName,
        lastName: data.lastName,
        role: 'user' as const,
        isEmailVerified: false,
        createdAt: new Date()
      },
      token: this.generateMockToken(),
      refreshToken: this.generateMockRefreshToken()
    };
  }
  
  private async mockRefreshToken(refreshToken: string): Promise<any> {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 500));
    
    return {
      success: true,
      token: this.generateMockToken()
    };
  }
  
  private generateMockToken(): string {
    const header = btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
    const payload = btoa(JSON.stringify({
      sub: '1',
      email: 'demo@example.com',
      iat: Math.floor(Date.now() / 1000),
      exp: Math.floor(Date.now() / 1000) + (24 * 60 * 60) // 24 hours
    }));
    const signature = btoa('mock-signature');
    
    return `${header}.${payload}.${signature}`;
  }
  
  private generateMockRefreshToken(): string {
    return btoa(`refresh-${Date.now()}-${Math.random()}`);
  }
  
  // Getters
  get currentUser(): User | null {
    return this.authState.value.user;
  }
  
  get isAuthenticated(): boolean {
    return this.authState.value.isAuthenticated;
  }
  
  get token(): string | null {
    return this.authState.value.token;
  }
  
  // Password reset functionality
  async requestPasswordReset(email: string): Promise<{ success: boolean; error?: string }> {
    try {
      // Validate email
      if (!this.validateEmail(email)) {
        return { success: false, error: 'Please enter a valid email address' };
      }
      
      // Generate reset token (in production, this should be done on the backend)
      const resetToken = this.generateResetToken();
      
      // Store reset token temporarily (in production, store on backend)
      localStorage.setItem(`reset_token_${email}`, JSON.stringify({
        token: resetToken,
        expires: Date.now() + (24 * 60 * 60 * 1000) // 24 hours
      }));
      
      // Send password reset email
      this.emailService.sendPasswordResetEmail(email, 'User', resetToken).subscribe({
        next: () => console.log('Password reset email sent successfully'),
        error: (error) => console.error('Failed to send password reset email:', error)
      });
      
      // Track password reset request
      this.analyticsService.trackEvent({
        event: 'password_reset_request',
        event_category: 'engagement',
        event_label: 'password_reset',
        custom_parameters: {
          email: email
        }
      });
      
      return { success: true };
    } catch (error) {
      console.error('Password reset request error:', error);
      return { success: false, error: 'Failed to send password reset email' };
    }
  }
  
  async resetPassword(token: string, newPassword: string, email: string): Promise<{ success: boolean; error?: string }> {
    try {
      // Validate new password
      const passwordValidation = this.validatePassword(newPassword);
      if (!passwordValidation.isValid) {
        return { success: false, error: passwordValidation.errors[0] };
      }
      
      // Verify reset token
      const storedData = localStorage.getItem(`reset_token_${email}`);
      if (!storedData) {
        return { success: false, error: 'Invalid or expired reset token' };
      }
      
      const { token: storedToken, expires } = JSON.parse(storedData);
      
      if (storedToken !== token || Date.now() > expires) {
        localStorage.removeItem(`reset_token_${email}`);
        return { success: false, error: 'Invalid or expired reset token' };
      }
      
      // In production, update password on backend
      console.log('Password reset successful for:', email);
      
      // Clean up reset token
      localStorage.removeItem(`reset_token_${email}`);
      
      // Track password reset completion
      this.analyticsService.trackEvent({
        event: 'password_reset_complete',
        event_category: 'engagement',
        event_label: 'password_reset',
        custom_parameters: {
          email: email
        }
      });
      
      return { success: true };
    } catch (error) {
      console.error('Password reset error:', error);
      return { success: false, error: 'Failed to reset password' };
    }
  }
  
  private generateResetToken(): string {
    return btoa(`reset-${Date.now()}-${Math.random()}`).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
  }
}