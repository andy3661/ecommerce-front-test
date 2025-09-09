import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { CartService } from './services/cart.service';
import { SecurityService } from './services/security.service';
import { SeoService } from './services/seo.service';
import { filter } from 'rxjs/operators';
import { Meta, Title } from '@angular/platform-browser';
import { SearchAutocompleteComponent } from './components/search-autocomplete/search-autocomplete.component';
import { ButtonModule } from 'primeng/button';
import { BadgeModule } from 'primeng/badge';
import { MenuModule } from 'primeng/menu';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule, 
    RouterModule, 
    SearchAutocompleteComponent,
    ButtonModule,
    BadgeModule,
    MenuModule
  ],
  template: `
    <div class="min-h-screen bg-gray-50">
      <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
              <h1 class="text-xl font-bold text-gray-900">{{ title }}</h1>
            </div>
            
            <!-- Search Bar -->
            <div class="hidden md:block flex-1 max-w-2xl mx-8">
              <app-search-autocomplete></app-search-autocomplete>
            </div>
            
            <div class="flex items-center space-x-6">
              <a routerLink="/" class="text-gray-600 hover:text-gray-900 transition-colors">Home</a>
              <a routerLink="/products" class="text-gray-600 hover:text-gray-900 transition-colors">Products</a>
              <a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">About</a>
              <a href="#" class="text-gray-600 hover:text-gray-900 transition-colors">Contact</a>
              
              <!-- Cart -->
              <a routerLink="/cart" class="relative text-gray-600 hover:text-gray-900 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13l-1.5 6m0 0h9M17 21a2 2 0 100-4 2 2 0 000 4zM9 21a2 2 0 100-4 2 2 0 000 4z"></path>
                </svg>
                <span *ngIf="cartService.itemCount() > 0" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">{{ cartService.itemCount() }}</span>
              </a>
              
              <!-- Authentication Links -->
              <div *ngIf="!securityService.isAuthenticated" class="flex items-center space-x-4">
                <a routerLink="/auth/login" class="text-gray-600 hover:text-gray-900 transition-colors">Login</a>
                <a routerLink="/auth/register" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">Sign Up</a>
              </div>
              
              <!-- User Menu -->
              <div *ngIf="securityService.isAuthenticated" class="flex items-center space-x-4">
                <div class="relative group">
                  <button class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>{{ securityService.getCurrentUser()?.firstName || 'Account' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                  </button>
                  
                  <!-- Dropdown Menu -->
                  <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                    <a routerLink="/account/dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Dashboard</a>
                    <a routerLink="/account/profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                    <a routerLink="/account/orders" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Orders</a>
                    <a routerLink="/account/wishlist" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Wishlist</a>
                    <div *ngIf="securityService.getCurrentUser()?.role === 'admin'" class="border-t border-gray-100">
                      <a routerLink="/admin/dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Panel</a>
                    </div>
                    <div class="border-t border-gray-100">
                      <button (click)="logout()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>
      <main>
        <router-outlet></router-outlet>
      </main>
    </div>
  `,
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {
  title = 'E-commerce Store';
  
  private router = inject(Router);
  private meta = inject(Meta);
  private titleService = inject(Title);
  private seoService = inject(SeoService);
  cartService = inject(CartService);
  securityService = inject(SecurityService);
  
  ngOnInit() {
    // Set default meta tags using SEO service
    this.seoService.setBasicSEO({
      title: 'E-commerce Store - Premium Online Shopping',
      description: 'Modern e-commerce store with fast, scalable Angular 20 SSR architecture',
      keywords: 'ecommerce, online store, shopping, products, angular, ssr',
      type: 'website'
    });
    
    // Handle route changes for SEO
    this.router.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe(() => {
        this.seoService.handleRouteChange(this.router.url);
      });
    
    // Initialize security service
    this.securityService.initializeAuth();
  }
  
  async logout(): Promise<void> {
    try {
      await this.securityService.logout();
      this.router.navigate(['/']);
    } catch (error) {
      console.error('Logout error:', error);
    }
  }
  

}
