import { Injectable, signal, computed, inject } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map, catchError, tap } from 'rxjs/operators';
import { AnalyticsService } from './analytics.service';
import { EmailService } from './email.service';
import { SecurityService } from './security.service';
import { ApiService, ApiResponse } from './api.service';
import { of } from 'rxjs';

export interface CartItem {
  id: string;
  productId: string;
  name: string;
  price: number;
  originalPrice?: number;
  quantity: number;
  imageUrl?: string;
  variant?: {
    color?: { name: string; value: string };
    size?: { name: string; value: string };
  };
  inStock: boolean;
  maxQuantity: number;
}

export interface CartSummary {
  subtotal: number;
  tax: number;
  shipping: number;
  discount: number;
  total: number;
  itemCount: number;
}

@Injectable({
  providedIn: 'root'
})
export class CartService {
  private readonly STORAGE_KEY = 'ecommerce_cart';
  private readonly TAX_RATE = 0.08; // 8% tax
  private readonly FREE_SHIPPING_THRESHOLD = 50;
  private readonly SHIPPING_COST = 9.99;

  private analyticsService = inject(AnalyticsService);
  private emailService = inject(EmailService);
  private securityService = inject(SecurityService);
  private apiService = inject(ApiService);

  // Reactive state using signals
  private cartItems = signal<CartItem[]>(this.loadCartFromStorage());
  
  // Public computed signals
  items = this.cartItems.asReadonly();
  itemCount = computed(() => this.cartItems().reduce((sum, item) => sum + item.quantity, 0));
  
  summary = computed(() => {
    const items = this.cartItems();
    const subtotal = items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const tax = subtotal * this.TAX_RATE;
    const shipping = subtotal >= this.FREE_SHIPPING_THRESHOLD ? 0 : this.SHIPPING_COST;
    const discount = 0; // TODO: Implement discount logic
    const total = subtotal + tax + shipping - discount;
    
    return {
      subtotal,
      tax,
      shipping,
      discount,
      total,
      itemCount: this.itemCount()
    } as CartSummary;
  });

  // Legacy BehaviorSubject for components that need observables
  private cartSubject = new BehaviorSubject<CartItem[]>(this.cartItems());
  cart$ = this.cartSubject.asObservable();

  constructor() {
    // Sync signal changes with BehaviorSubject and localStorage
    this.cartItems.set(this.loadCartFromStorage());
    
    // Load cart from backend if user is authenticated
    this.securityService.authState$.subscribe(authState => {
      if (authState.isAuthenticated) {
        this.syncCartWithBackend();
      }
    });
  }

  private loadCartFromStorage(): CartItem[] {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch (error) {
      console.error('Error loading cart from storage:', error);
      return [];
    }
  }

  private saveCartToStorage(items: CartItem[]): void {
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
    } catch (error) {
      console.error('Error saving cart to storage:', error);
    }
  }

  private updateCart(items: CartItem[]): void {
    this.cartItems.set(items);
    this.cartSubject.next(items);
    this.saveCartToStorage(items);
  }

  addItem(product: any, quantity: number = 1, variant?: any): Observable<boolean> {
    const currentItems = [...this.cartItems()];
    
    // Create unique item ID based on product and variants
    const itemId = this.generateItemId(product.id, variant);
    
    // Check if item already exists
    const existingItemIndex = currentItems.findIndex(item => item.id === itemId);
    
    if (existingItemIndex >= 0) {
      // Update existing item quantity
      const existingItem = currentItems[existingItemIndex];
      const newQuantity = existingItem.quantity + quantity;
      
      if (newQuantity > existingItem.maxQuantity) {
        return of(false); // Cannot add more than max quantity
      }
      
      currentItems[existingItemIndex] = {
        ...existingItem,
        quantity: newQuantity
      };
    } else {
      // Add new item
      const newItem: CartItem = {
        id: itemId,
        productId: product.id,
        name: product.name,
        price: product.price,
        originalPrice: product.originalPrice,
        quantity,
        imageUrl: product.imageUrl || product.images?.[0]?.url,
        variant: variant ? {
          color: variant.color,
          size: variant.size
        } : undefined,
        inStock: product.inStock,
        maxQuantity: product.stockQuantity || 99
      };
      
      currentItems.push(newItem);
    }
    
    // Update local cart first
    this.updateCart(currentItems);
    
    // Sync with backend if authenticated
    if (this.securityService.isAuthenticated) {
      return this.addItemToBackend(product.id, quantity, variant).pipe(
        map(() => {
          // Track add to cart event
          this.analyticsService.trackEvent({
            event: 'add_to_cart',
            event_category: 'ecommerce',
            event_label: product.name,
            value: product.price * quantity,
            custom_parameters: {
              item_id: product.id,
              item_name: product.name,
              currency: 'USD',
              quantity
            }
          });
          return true;
        }),
        catchError(error => {
          console.error('Error adding item to backend cart:', error);
          return of(true); // Still return true as local cart was updated
        })
      );
    } else {
      // Track add to cart event for guest users
      this.analyticsService.trackEvent({
        event: 'add_to_cart',
        event_category: 'ecommerce',
        event_label: product.name,
        value: product.price * quantity,
        custom_parameters: {
          item_id: product.id,
          item_name: product.name,
          currency: 'USD',
          quantity
        }
      });
      return of(true);
    }
  }

  removeItem(itemId: string): void {
    const itemToRemove = this.cartItems().find(item => item.id === itemId);
    const currentItems = this.cartItems().filter(item => item.id !== itemId);
    
    if (itemToRemove) {
      // Track remove from cart event
      this.analyticsService.trackEvent({
        event: 'remove_from_cart',
        event_category: 'ecommerce',
        event_label: itemToRemove.name,
        value: itemToRemove.price * itemToRemove.quantity,
        custom_parameters: {
          item_id: itemToRemove.productId,
          item_name: itemToRemove.name,
          currency: 'USD',
          quantity: itemToRemove.quantity
        }
      });
    }
    
    this.updateCart(currentItems);
  }

  updateQuantity(itemId: string, quantity: number): boolean {
    if (quantity <= 0) {
      this.removeItem(itemId);
      return true;
    }
    
    const currentItems = [...this.cartItems()];
    const itemIndex = currentItems.findIndex(item => item.id === itemId);
    
    if (itemIndex >= 0) {
      const item = currentItems[itemIndex];
      
      if (quantity > item.maxQuantity) {
        return false;
      }
      
      currentItems[itemIndex] = {
        ...item,
        quantity
      };
      
      this.updateCart(currentItems);
      return true;
    }
    
    return false;
  }

  clearCart(): void {
    // Track cart clear event
    const currentItems = this.cartItems();
    if (currentItems.length > 0) {
      this.analyticsService.trackEvent({
        event: 'clear_cart',
        event_category: 'ecommerce',
        custom_parameters: {
          items_count: currentItems.length,
          cart_value: this.summary().total
        }
      });
    }
    
    this.updateCart([]);
  }

  getItem(itemId: string): CartItem | undefined {
    return this.cartItems().find(item => item.id === itemId);
  }

  isInCart(productId: string, variant?: any): boolean {
    const itemId = this.generateItemId(productId, variant);
    return this.cartItems().some(item => item.id === itemId);
  }

  getItemQuantity(productId: string, variant?: any): number {
    const itemId = this.generateItemId(productId, variant);
    const item = this.cartItems().find(item => item.id === itemId);
    return item?.quantity || 0;
  }

  private generateItemId(productId: string, variant?: any): string {
    let id = productId;
    
    if (variant) {
      if (variant.color) {
        id += `-color-${variant.color.value || variant.color.name}`;
      }
      if (variant.size) {
        id += `-size-${variant.size.value || variant.size.name}`;
      }
    }
    
    return id;
  }

  // Utility methods
  getTotalSavings(): number {
    return this.cartItems().reduce((savings, item) => {
      if (item.originalPrice && item.originalPrice > item.price) {
        return savings + ((item.originalPrice - item.price) * item.quantity);
      }
      return savings;
    }, 0);
  }

  getEstimatedDelivery(): Date {
    const deliveryDate = new Date();
    deliveryDate.setDate(deliveryDate.getDate() + 3); // 3 days from now
    return deliveryDate;
  }

  // Validation methods
  validateCart(): { isValid: boolean; errors: string[] } {
    const errors: string[] = [];
    const items = this.cartItems();
    
    if (items.length === 0) {
      errors.push('Cart is empty');
    }
    
    items.forEach(item => {
      if (!item.inStock) {
        errors.push(`${item.name} is out of stock`);
      }
      
      if (item.quantity > item.maxQuantity) {
        errors.push(`${item.name} quantity exceeds available stock`);
      }
    });
    
    return {
      isValid: errors.length === 0,
      errors
    };
  }

  // Analytics tracking methods
  trackBeginCheckout(): void {
    const items = this.cartItems();
    const summary = this.summary();
    
    if (items.length > 0) {
      const analyticsItems = items.map(item => ({
        item_id: item.productId,
        item_name: item.name,
        item_category: 'product', // You might want to add category to CartItem interface
        price: item.price,
        quantity: item.quantity,
        currency: 'USD'
      }));
      
      this.analyticsService.trackBeginCheckout(analyticsItems, summary.total);
    }
  }

  trackPurchase(transactionId: string): void {
    const items = this.cartItems();
    const summary = this.summary();
    
    if (items.length > 0) {
      const analyticsItems = items.map(item => ({
        item_id: item.productId,
        item_name: item.name,
        item_category: 'product',
        price: item.price,
        quantity: item.quantity,
        currency: 'USD'
      }));
      
      this.analyticsService.trackPurchase(transactionId, analyticsItems, summary.total);
    }
  }

  // Backend synchronization methods
  private syncCartWithBackend(): void {
    if (!this.securityService.isAuthenticated) {
      return;
    }

    this.apiService.get<CartItem[]>('cart').subscribe({
      next: (response) => {
        if (response.success && response.data) {
          // Merge backend cart with local cart
          const backendItems = response.data;
          const localItems = this.cartItems();
          
          // For now, prioritize backend cart
          // In production, you might want more sophisticated merging logic
          if (backendItems.length > 0) {
            this.updateCart(backendItems);
          } else if (localItems.length > 0) {
            // Sync local cart to backend
            this.syncLocalCartToBackend(localItems);
          }
        }
      },
      error: (error) => {
        console.error('Error syncing cart with backend:', error);
      }
    });
  }

  private syncLocalCartToBackend(items: CartItem[]): void {
    const cartData = {
      items: items.map(item => ({
        product_id: item.productId,
        quantity: item.quantity,
        product_variant: item.variant
      }))
    };

    this.apiService.post('cart/sync', cartData).subscribe({
      next: (response) => {
        console.log('Local cart synced to backend successfully');
      },
      error: (error) => {
        console.error('Error syncing local cart to backend:', error);
      }
    });
  }

  private addItemToBackend(productId: string, quantity: number, variant?: any): Observable<any> {
    const data = {
      product_id: productId,
      quantity,
      product_variant: variant
    };

    return this.apiService.post('cart/add', data);
  }

  private updateItemInBackend(productId: string, quantity: number, variant?: any): Observable<any> {
    const data = {
      product_id: productId,
      quantity,
      product_variant: variant
    };

    return this.apiService.put('cart/update', data);
  }

  private removeItemFromBackend(productId: string, variant?: any): Observable<any> {
    const data = {
      product_id: productId,
      product_variant: variant
    };

    return this.apiService.post('cart/remove', data);
  }

  private clearBackendCart(): Observable<any> {
    return this.apiService.delete('cart/clear');
  }

  // Get cart count from backend
  getCartCountFromBackend(): Observable<number> {
    return this.apiService.get<{ count: number }>('cart/count').pipe(
      map(response => response.data?.count || 0),
      catchError(() => of(0))
    );
  }

  // Apply coupon
  applyCoupon(couponCode: string): Observable<{ success: boolean; discount: number; message: string }> {
    return this.apiService.post<{ discount: number; message: string }>('cart/coupon', {
      coupon_code: couponCode
    }).pipe(
      map(response => ({
        success: response.success,
        discount: response.data?.discount || 0,
        message: response.message || 'Coupon applied successfully'
      })),
      catchError(error => of({
        success: false,
        discount: 0,
        message: error.message || 'Invalid coupon code'
      }))
    );
  }

  // Remove coupon
  removeCoupon(): Observable<{ success: boolean; message: string }> {
    return this.apiService.delete<any>('cart/coupon').pipe(
      map(response => ({
        success: response.success,
        message: response.message || 'Coupon removed successfully'
      })),
      catchError(error => of({
        success: false,
        message: error.message || 'Error removing coupon'
      }))
    );
  }
      
      this.analyticsService.trackPurchase(transactionId, analyticsItems, summary.total);
      
      // Send order confirmation email
      const currentUser = this.securityService.getCurrentUser();
      if (currentUser && currentUser.email) {
        this.emailService.sendOrderConfirmationEmail(currentUser.email, {
          firstName: currentUser.firstName,
          orderNumber: transactionId,
          total: summary.total,
          items: items.map((item: any) => ({
            name: item.name,
            quantity: item.quantity,
            price: `$${item.price.toFixed(2)}`
          })),
          shippingAddress: 'Standard shipping address',
          estimatedDelivery: this.getEstimatedDelivery().toLocaleDateString()
        }).subscribe({
          next: () => console.log('Order confirmation email sent successfully'),
          error: (error) => console.error('Failed to send order confirmation email:', error)
        });
      }
    }
  }

  // Legacy method for backward compatibility
  addToCart(item: any): boolean {
    return this.addItem(item, 1, item.variant);
  }
  
  // Send abandoned cart email
  sendAbandonedCartEmail(): void {
    const currentUser = this.securityService.getCurrentUser();
    const items = this.cartItems();
    
    if (currentUser && currentUser.email && items.length > 0) {
      const cartData = {
        firstName: currentUser.firstName,
        items: items.map(item => ({
          name: item.name,
          price: `$${item.price.toFixed(2)}`,
          image: item.imageUrl
        })),
        total: this.summary().total
      };
      
      this.emailService.sendAbandonedCartEmail(currentUser.email, cartData).subscribe({
        next: () => console.log('Abandoned cart email sent successfully'),
        error: (error) => console.error('Failed to send abandoned cart email:', error)
      });
    }
  }
}