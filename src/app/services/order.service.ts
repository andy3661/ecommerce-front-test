import { Injectable, signal, computed, inject } from '@angular/core';
import { BehaviorSubject, Observable, of, map, catchError, switchMap } from 'rxjs';
import { ApiService } from './api.service';
import { SecurityService } from './security.service';
import { AnalyticsService } from './analytics.service';
import { EmailService } from './email.service';
import { CartService } from './cart.service';

export interface OrderItem {
  id: string;
  productId: string;
  name: string;
  price: number;
  quantity: number;
  variant?: any;
  image?: string;
  sku?: string;
}

export interface ShippingAddress {
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
}

export interface BillingAddress extends ShippingAddress {
  sameAsShipping?: boolean;
}

export interface PaymentMethod {
  type: 'credit_card' | 'paypal' | 'bank_transfer' | 'cash_on_delivery';
  cardNumber?: string;
  expiryMonth?: string;
  expiryYear?: string;
  cvv?: string;
  cardholderName?: string;
  paypalEmail?: string;
  bankAccount?: string;
}

export interface Order {
  id: string;
  orderNumber: string;
  userId: string;
  status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled' | 'refunded';
  items: OrderItem[];
  subtotal: number;
  tax: number;
  shipping: number;
  discount: number;
  total: number;
  currency: string;
  shippingAddress: ShippingAddress;
  billingAddress: BillingAddress;
  paymentMethod: PaymentMethod;
  paymentStatus: 'pending' | 'paid' | 'failed' | 'refunded';
  trackingNumber?: string;
  notes?: string;
  createdAt: Date;
  updatedAt: Date;
  estimatedDelivery?: Date;
}

export interface OrderFilters {
  status?: string[];
  dateFrom?: Date;
  dateTo?: Date;
  minAmount?: number;
  maxAmount?: number;
  paymentStatus?: string[];
}

export interface OrderSummary {
  totalOrders: number;
  totalAmount: number;
  averageOrderValue: number;
  statusCounts: { [key: string]: number };
}

export interface CheckoutData {
  items: OrderItem[];
  shippingAddress: ShippingAddress;
  billingAddress: BillingAddress;
  paymentMethod: PaymentMethod;
  couponCode?: string;
  notes?: string;
}

@Injectable({
  providedIn: 'root'
})
export class OrderService {
  private apiService = inject(ApiService);
  private securityService = inject(SecurityService);
  private analyticsService = inject(AnalyticsService);
  private emailService = inject(EmailService);
  private cartService = inject(CartService);

  // Reactive state
  private ordersSubject = new BehaviorSubject<Order[]>([]);
  private currentOrderSubject = new BehaviorSubject<Order | null>(null);
  private loadingSubject = new BehaviorSubject<boolean>(false);

  // Signals
  orders = signal<Order[]>([]);
  currentOrder = signal<Order | null>(null);
  loading = signal<boolean>(false);

  // Computed values
  orderCount = computed(() => this.orders().length);
  totalSpent = computed(() => 
    this.orders().reduce((sum, order) => sum + order.total, 0)
  );
  averageOrderValue = computed(() => {
    const orders = this.orders();
    return orders.length > 0 ? this.totalSpent() / orders.length : 0;
  });

  // Observables for legacy compatibility
  orders$ = this.ordersSubject.asObservable();
  currentOrder$ = this.currentOrderSubject.asObservable();
  loading$ = this.loadingSubject.asObservable();

  constructor() {
    // Sync signals with BehaviorSubjects
    this.ordersSubject.subscribe(orders => this.orders.set(orders));
    this.currentOrderSubject.subscribe(order => this.currentOrder.set(order));
    this.loadingSubject.subscribe(loading => this.loading.set(loading));

    // Load user orders if authenticated
    if (this.securityService.isAuthenticated) {
      this.loadUserOrders();
    }

    // Subscribe to auth changes
    this.securityService.authState$.subscribe(authState => {
      if (authState.isAuthenticated) {
        this.loadUserOrders();
      } else {
        this.clearOrders();
      }
    });
  }

  // Get user orders
  getUserOrders(filters?: OrderFilters): Observable<Order[]> {
    this.setLoading(true);
    
    const params: any = {};
    if (filters) {
      if (filters.status?.length) params.status = filters.status.join(',');
      if (filters.dateFrom) params.date_from = filters.dateFrom.toISOString();
      if (filters.dateTo) params.date_to = filters.dateTo.toISOString();
      if (filters.minAmount) params.min_amount = filters.minAmount;
      if (filters.maxAmount) params.max_amount = filters.maxAmount;
      if (filters.paymentStatus?.length) params.payment_status = filters.paymentStatus.join(',');
    }

    return this.apiService.get<Order[]>('orders', params).pipe(
      map(response => {
        const orders = response.data || [];
        this.ordersSubject.next(orders);
        this.setLoading(false);
        return orders;
      }),
      catchError(error => {
        console.error('Error loading orders:', error);
        this.setLoading(false);
        return of([]);
      })
    );
  }

  // Get single order
  getOrder(orderId: string): Observable<Order | null> {
    this.setLoading(true);
    
    return this.apiService.get<Order>(`orders/${orderId}`).pipe(
      map(response => {
        const order = response.data || null;
        this.currentOrderSubject.next(order);
        this.setLoading(false);
        return order;
      }),
      catchError(error => {
        console.error('Error loading order:', error);
        this.setLoading(false);
        return of(null);
      })
    );
  }

  // Create order (checkout)
  createOrder(checkoutData: CheckoutData): Observable<{ success: boolean; order?: Order; message: string }> {
    this.setLoading(true);
    
    const orderData = {
      items: checkoutData.items.map(item => ({
        product_id: item.productId,
        quantity: item.quantity,
        price: item.price,
        product_variant: item.variant
      })),
      shipping_address: checkoutData.shippingAddress,
      billing_address: checkoutData.billingAddress,
      payment_method: checkoutData.paymentMethod,
      coupon_code: checkoutData.couponCode,
      notes: checkoutData.notes
    };

    return this.apiService.post<Order>('orders', orderData).pipe(
      map(response => {
        this.setLoading(false);
        
        if (response.success && response.data) {
          const order = response.data;
          
          // Update orders list
          const currentOrders = this.ordersSubject.value;
          this.ordersSubject.next([order, ...currentOrders]);
          
          // Set as current order
          this.currentOrderSubject.next(order);
          
          // Clear cart after successful order
          this.cartService.clearCart();
          
          // Track purchase
          this.trackOrderCreated(order);
          
          // Send confirmation email
          this.sendOrderConfirmationEmail(order);
          
          return {
            success: true,
            order,
            message: response.message || 'Order created successfully'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Failed to create order'
        };
      }),
      catchError(error => {
        this.setLoading(false);
        console.error('Error creating order:', error);
        return of({
          success: false,
          message: error.message || 'Failed to create order'
        });
      })
    );
  }

  // Cancel order
  cancelOrder(orderId: string, reason?: string): Observable<{ success: boolean; message: string }> {
    const data = { reason };
    
    return this.apiService.post<any>(`orders/${orderId}/cancel`, data).pipe(
      map(response => {
        if (response.success) {
          // Update order in local state
          this.updateOrderStatus(orderId, 'cancelled');
          
          // Track cancellation
          this.analyticsService.trackEvent('order_cancelled', {
            order_id: orderId,
            reason: reason || 'user_request'
          });
        }
        
        return {
          success: response.success,
          message: response.message || 'Order cancelled successfully'
        };
      }),
      catchError(error => {
        console.error('Error cancelling order:', error);
        return of({
          success: false,
          message: error.message || 'Failed to cancel order'
        });
      })
    );
  }

  // Request refund
  requestRefund(orderId: string, reason: string, items?: { itemId: string; quantity: number }[]): Observable<{ success: boolean; message: string }> {
    const data = {
      reason,
      items: items || []
    };
    
    return this.apiService.post<any>(`orders/${orderId}/refund`, data).pipe(
      map(response => {
        if (response.success) {
          // Track refund request
          this.analyticsService.trackEvent('refund_requested', {
            order_id: orderId,
            reason,
            items_count: items?.length || 0
          });
        }
        
        return {
          success: response.success,
          message: response.message || 'Refund request submitted successfully'
        };
      }),
      catchError(error => {
        console.error('Error requesting refund:', error);
        return of({
          success: false,
          message: error.message || 'Failed to request refund'
        });
      })
    );
  }

  // Track order
  trackOrder(orderNumber: string): Observable<{ success: boolean; order?: Order; trackingInfo?: any; message: string }> {
    return this.apiService.get<{ order: Order; tracking: any }>(`orders/track/${orderNumber}`).pipe(
      map(response => {
        if (response.success && response.data) {
          return {
            success: true,
            order: response.data.order,
            trackingInfo: response.data.tracking,
            message: 'Order found'
          };
        }
        
        return {
          success: false,
          message: response.message || 'Order not found'
        };
      }),
      catchError(error => {
        console.error('Error tracking order:', error);
        return of({
          success: false,
          message: error.message || 'Failed to track order'
        });
      })
    );
  }

  // Get order summary/statistics
  getOrderSummary(dateFrom?: Date, dateTo?: Date): Observable<OrderSummary> {
    const params: any = {};
    if (dateFrom) params.date_from = dateFrom.toISOString();
    if (dateTo) params.date_to = dateTo.toISOString();
    
    return this.apiService.get<OrderSummary>('orders/summary', params).pipe(
      map(response => response.data || {
        totalOrders: 0,
        totalAmount: 0,
        averageOrderValue: 0,
        statusCounts: {}
      }),
      catchError(error => {
        console.error('Error loading order summary:', error);
        return of({
          totalOrders: 0,
          totalAmount: 0,
          averageOrderValue: 0,
          statusCounts: {}
        });
      })
    );
  }

  // Reorder (create new order from existing order)
  reorder(orderId: string): Observable<{ success: boolean; message: string }> {
    return this.apiService.post<any>(`orders/${orderId}/reorder`, {}).pipe(
      switchMap(response => {
        if (response.success) {
          // Reload cart to show added items
          return this.cartService.loadCart().pipe(
            map(() => ({
              success: true,
              message: response.message || 'Items added to cart successfully'
            }))
          );
        }
        
        return of({
          success: false,
          message: response.message || 'Failed to reorder'
        });
      }),
      catchError(error => {
        console.error('Error reordering:', error);
        return of({
          success: false,
          message: error.message || 'Failed to reorder'
        });
      })
    );
  }

  // Private helper methods
  private loadUserOrders(): void {
    this.getUserOrders().subscribe();
  }

  private clearOrders(): void {
    this.ordersSubject.next([]);
    this.currentOrderSubject.next(null);
  }

  private setLoading(loading: boolean): void {
    this.loadingSubject.next(loading);
  }

  private updateOrderStatus(orderId: string, status: Order['status']): void {
    const orders = this.ordersSubject.value;
    const updatedOrders = orders.map(order => 
      order.id === orderId ? { ...order, status } : order
    );
    this.ordersSubject.next(updatedOrders);
    
    // Update current order if it matches
    const currentOrder = this.currentOrderSubject.value;
    if (currentOrder?.id === orderId) {
      this.currentOrderSubject.next({ ...currentOrder, status });
    }
  }

  private trackOrderCreated(order: Order): void {
    // Track purchase completion
    this.cartService.trackPurchase(order.id);
    
    // Track order created event
    this.analyticsService.trackEvent('order_created', {
      order_id: order.id,
      order_number: order.orderNumber,
      total: order.total,
      items_count: order.items.length,
      payment_method: order.paymentMethod.type
    });
  }

  private sendOrderConfirmationEmail(order: Order): void {
    const user = this.securityService.getCurrentUser();
    if (user?.email) {
      this.emailService.sendOrderConfirmation(user.email, order).subscribe({
        next: () => console.log('Order confirmation email sent'),
        error: (error) => console.error('Error sending confirmation email:', error)
      });
    }
  }

  // Utility methods
  getOrderStatusColor(status: Order['status']): string {
    const colors = {
      pending: '#fbbf24',
      processing: '#3b82f6',
      shipped: '#8b5cf6',
      delivered: '#10b981',
      cancelled: '#ef4444',
      refunded: '#6b7280'
    };
    return colors[status] || '#6b7280';
  }

  getOrderStatusText(status: Order['status']): string {
    const texts = {
      pending: 'Pending',
      processing: 'Processing',
      shipped: 'Shipped',
      delivered: 'Delivered',
      cancelled: 'Cancelled',
      refunded: 'Refunded'
    };
    return texts[status] || status;
  }

  formatOrderNumber(orderNumber: string): string {
    return `#${orderNumber}`;
  }

  canCancelOrder(order: Order): boolean {
    return ['pending', 'processing'].includes(order.status);
  }

  canRequestRefund(order: Order): boolean {
    return ['delivered'].includes(order.status);
  }

  canTrackOrder(order: Order): boolean {
    return ['shipped', 'delivered'].includes(order.status) && !!order.trackingNumber;
  }
}